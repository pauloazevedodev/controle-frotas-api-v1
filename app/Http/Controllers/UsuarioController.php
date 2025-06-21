<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function insertUsuario(Request $request)
    {
        // dd($request);
        $lastCodUsur = DB::table('USUARIOS')->max('cod_usur');
        $newCodUsur = $lastCodUsur + 1;

        $usuario = DB::table('USUARIOS')->insert([
            'cod_usur' => $newCodUsur, 
            'nome' => $request->input('nome'),
            'cpf' => $request->input('cpf'),
            'email' => $request->input('email'),
            'status' => $request->input('status'),
            'id_tipo_usuario' => $request->input('tipoUsuario'),
            'dt_cadastro' => now(),
            'password' => '123456', 
        ]);
        return response()->json(['message' => 'Usuário salvo com sucesso!'], 201);
    }


    public function getUsuarios(Request $request)
{
    $query = DB::table('usuarios as usur')
        ->Leftjoin('tipos_usuario as tipos', 'usur.id_tipo_usuario', '=', 'tipos.id')
        ->select(
            'usur.cod_usur',
            'usur.nome',
            'usur.cpf',
            'usur.email',
            'usur.status',
            'tipos.descricao'
        );

    if ($request->has('search') && $request->input('search') != '') {
        $searchTerm = $request->input('search');

        $query->where(function($subQuery) use ($searchTerm) {
            $subQuery->where('usur.nome', 'LIKE', '%' . $searchTerm . '%')
                     ->orWhere('usur.email', 'LIKE', '%' . $searchTerm . '%')
                     ->orWhere('usur.cpf', 'LIKE', '%' . $searchTerm . '%');
        });
    }

    $usuarios = $query->get();

    return response($usuarios, 200);
}
    public function getUsur(Request $request)
{

    // dd($request);
    $usur = DB::table('usuarios as usur')
        ->leftJoin('tipos_usuario as tipos', 'usur.id_tipo_usuario', '=', 'tipos.id')
        ->select(
            'usur.cod_usur',
            'usur.nome',
            'usur.cpf',
            'usur.email',
            'usur.status',
            'tipos.descricao'
        )
        // ->where('usur.nome', $request->nome)
        ->get();

    if ($usur) {
        return response()->json([
            'success' => true,
            'data' => $usur
        ], 200);
    }

    return response()->json([
        'success' => false,
        'message' => 'Usuário não encontrado'
    ], 404);
}

    public function deleteUsuarios(Request $request)
{   
    $codUsur = $request->input('codUsur'); 

    if (!$codUsur) {
        return response()->json(['success' => false, 'message' => 'Código do usuário não fornecido'], 400);
    }

  
    $deleted = DB::table('usuarios')->where('cod_usuario', $codUsur)->delete();
    
    $deletedTipo = DB::table('tipos_usuario')->where('cod_usuario', $codUsur)->delete();

    if ($deleted && $deletedTipo) {
        return response()->json(['success' => true, 'message' => 'Usuário excluído com sucesso!'], 200);
    } else {
        return response()->json(['success' => false, 'message' => 'Erro ao excluir usuário ou usuário não encontrado'], 400);
    }
}
public function editUsuarios(Request $request)
{
    $usuario = DB::table('usuarios')->where('cpf', $request->cpf)->first();
    if (!$usuario) {
        return response()->json([
            'status' => 'error',
            'message' => 'Usuário não encontrado',
        ], 404);
    }

    DB::table('usuarios as usur')
    ->where('usur.cod_usur', $request->codUsur)  
    ->update([
        'usur.nome' => $request->nome,
        'usur.cpf' => $request->cpf,
        'usur.email' => $request->email,
        'usur.status' => $request->status,
        'usur.id_tipo_usuario' => $request->tipo,
    ]);


    return response()->json([
        'status' => 'success',
        'message' => 'Usuário atualizado com sucesso',
    ], 200);
}
}
