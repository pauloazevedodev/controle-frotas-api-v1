<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash; 
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $usuario = User::join('tipos_usuario', 'usuarios.id_tipo_usuario', '=', 'tipos_usuario.id')->where('nome', $request->usuario)->first();
    
        if (!$usuario || $usuario->password !== $request->password) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário ou senha inválidos'
            ], 401);
        }
    
        $token = auth('api')->login($usuario);
    
       
        return response()->json([
            'success' => true,
            'message' => 'Login bem-sucedido!',
            'token' => $token,
            'data' => $usuario
        ]);
    }

public function logout()
{
    Auth::guard('api')->logout();
    return response()->json(['message' => 'Logout realizado com sucesso']);
}

    public function updateTermo(Request $request)
    {
        $info = $request->all();
        $userId = $info['id'];
        $status = $info['status'];
    
        DB::table('usuarios')
            ->where('cod_usur', $userId)
            ->update(['termo' => $status]);
    
            return response()->json([
                'success' => true,
                'message' => 'Termo atualizado!'
            ]);    }
}