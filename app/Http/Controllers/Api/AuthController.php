<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    private function createToken(User $user): string
    {
        // return plain text token (Sanctum)
        return $user->createToken('auth_token')->plainTextToken;
    }

    private function createRefreshToken(User $user)
    {
        $plain = bin2hex(random_bytes(64));

        $hashed = hash('sha256', $plain);

        $tokenId = Str::uuid()->toString();

        Redis::setex("refresh_token:{$user->id}:{$tokenId}",
            60 * 60 * 24 * 30,
            $hashed
        );

        return [
            'tokenId' => $tokenId,
            'plainToken' => $plain
        ];
    }

    public function refreshToken(RefreshTokenRequest $request){
        $data = $request->validated();

        $hashed = Redis::get("refresh_token:{$data['userId']}:{$data['tokenId']}");
        if (!$hashed || !hash_equals($hashed, hash('sha256', $data['refreshToken']))) {
            return response()->json(['status'=>false,'msg'=>'Invalid refresh token'], 401);
        }

        $user = User::find($data['userId']);
        if (!$user) return response()->json(['status'=>false,'msg'=>'User not found'], 404);

        $accessToken = $this->createToken($user);

        Redis::del("refresh_token:{$data['userId']}:{$data['tokenId']}");
        $newRefreshToken = $this->createRefreshToken($user);

        return response()->json([
            'status' => true,
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'data' => new UserResource($user)
        ], 200);
    }

    private function generateKey()
    {
        do {
            $key = bin2hex(random_bytes(8));
        } while (User::where('key', $key)->exists());
        return $key;
    }

    private function throttleKey(Request $request, string $identifier)
    {
        $key = Str::lower($identifier).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json(['status' => false, 'msg' => "Too many attempts. Try again in {$seconds} seconds."], 429);
        }
        RateLimiter::hit($key, 60);
        return null;
    }

    public function index()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['status' => false, 'msg' => 'Unauthenticated.'], 401);
        return (new UserResource($user))->response();
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $identifier = $request->filled('email') ? $request->email : $request->name;
        $throttle = $this->throttleKey($request, $identifier);
        if ($throttle) return $throttle;

        $credentials = [];
        if ($request->filled('name')) $credentials = $request->only('name','password');
        if ($request->filled('email')) $credentials = $request->only('email','password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['status'=>false,'msg'=>'Invalid Credentials'],401);
        }

        $user = Auth::user();
        if ($user->status==='deleted') {
            $user->tokens()->delete();
            return response()->json(['status'=>false,'msg'=>'Your account is deleted'],403);
        } elseif ($user->status==='blocked') {
            $user->tokens()->delete();
            Auth::logout();
            return response()->json(['status'=>false,'msg'=>'Your account is blocked. Contact support.'],403);
        }

        $token = $this->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json(['status'=>true,'access_token' => $token , 'refresh_token' => $refreshToken,'data'=> new UserResource($user)],200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['status' => false, 'msg' => 'Unauthenticated.'], 401);
        $user->currentAccessToken()?->delete();
        Redis::del("refresh_token:{$user->id}:{$request->tokenId}");

        return response()->json(['status'=>true,'msg'=>'logout'],200);
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'pending';

        $user = User::create($data);
        $user->key = Hash::make($this->generateKey());
        $user->save();

        $token = $this->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json(['status'=>true,'msg'=>'created','data'=> new UserResource($user) ,'access_token'=>$token , 'refresh_token' => $refreshToken],201);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();
        $data = $request->validated();

        if (!Hash::check($data['key'], $user->key)) return response()->json(['status'=>false,'msg'=>'invalid key'],401);
        if (!empty($data['password']) && Hash::check($data['password'],$user->password)) return response()->json(['status'=>false,'msg'=>'this password is old'],401);

        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        $data['key'] = Hash::make($this->generateKey());

        $user->update($data);
        return response()->json(['status'=>true,'msg'=>'updated','data'=> new UserResource($user)],200);
    }

    public function switchAccount(string $id)
    {
        $user = Auth::user();
        $user->currentAccessToken()->delete();
        $account = User::where('name',$user->name)->where('id',$id)->firstOrFail();
        $account->tokens()->delete();
        $token = $this->createToken($account);
        return response()->json(['status'=>true,'msg'=>'switched','token'=>$token],200);
    }

    public function softDelete(Request $request)
    {
        $user = User::query()
            ->when($request->email ?? null, fn($q,$email)=>$q->where('email',$email))
            ->when($request->phone ?? null, fn($q,$phone)=>$q->where('phone',$phone))
            ->first();

        if (!$user) return response()->json(['status'=>false,'msg'=>'no account found'],404);

        $user->status='deleted';
        $user->verify_key=null;
        $user->tokens()->delete();
        $user->save();

        $user->delete();

        return response()->json(['status'=>true,'msg'=>'deleted'],200);
    }

    public function resetAccount(RefreshTokenRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email',$data['userId'])->first();
        if (!$user || $user->status!=='deleted') return response()->json(['status'=>false,'msg'=>'No soft-deleted account found'],404);
        $user->status='active';
        $user->save();
        $token = $this->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return response()->json(['status'=>true,'access_token' => $token , 'refresh_token' => $refreshToken,'data'=> new UserResource($user)],200);

    }

    public function hardDelete()
    {
        $user = Auth::user();
        $user->tokens()->delete();
        $user->delete();
        return response()->json(['status'=>true,'msg'=>'account permanently deleted'],200);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email',$data['email'])->first();
        if (!$user) return response()->json(['status'=>false,'msg'=>'no account found'],404);
        if (!Hash::check($data['key'],$user->key)) return response()->json(['status'=>false,'msg'=>'invalid key'],401);

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json(['status'=>true,'msg'=>'password reset successfully'],200);
    }

    public function blockUser(Request $request,string $id)
    {
        $admin = Auth::user();
        if ($admin->role!=='admin') return response()->json(['status'=>false,'msg'=>'unauthorized'],403);

        $user = User::findOrFail($id);
        $user->status='blocked';
        $user->tokens()->delete();
        $user->save();

        return response()->json(['status'=>true,'msg'=>'user blocked'],200);
    }
    public function unblockUser(Request $request,string $id)
    {
        $admin = Auth::user();
        if ($admin->role!=='admin') return response()->json(['status'=>false,'msg'=>'unauthorized'],403);

        $user = User::findOrFail($id);
        $user->status='active';
        $user->save();

        return response()->json(['status'=>true,'msg'=>'user unblocked'],200);
    }
}


?>
