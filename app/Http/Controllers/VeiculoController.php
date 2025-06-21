<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VeiculoController extends Controller
{
    public function getVeiculos(Request $request)
    {
        $searchTerm = $request->input('search');

        $veiculos = DB::table('VEICULOS as veic')
            ->join('TIPOS_VEICULO as tv', 'veic.id_tipo_veiculo', '=', 'tv.id')
            ->select(
                'veic.cod_veiculo',
                'veic.modelo',
                'veic.placa',
                'veic.ano',
                'veic.capacidade',
                'veic.dt_prox_manu',
                'veic.dt_ultim_manu',
                'tv.descricao as tipo_veiculo',
                'veic.status',
                'veic.obs_status',
            );

        if (!empty($searchTerm)) {
            $veiculos->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('veic.modelo', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('veic.placa', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        $veiculos = $veiculos->get();
        // dd($veiculos);
        return response($veiculos, 200);
    }
    public function getVeiculosDisponiveis(Request $request)
    {
        $info = $request->all();
        $veiculos = DB::table('VEICULOS as veic')
            ->join('TIPOS_VEICULO as tv', 'veic.id_tipo_veiculo', '=', 'tv.id')
            ->select(
                'veic.cod_veiculo',
                'veic.modelo',
                'veic.placa',
                'veic.ano',
                'veic.capacidade',
                // 'veic.dt_prox_manu',
                // 'veic.dt_ultim_manu',
                'tv.descricao as tipo_veiculo',
                'veic.status',
                )
                ->where('status', 'Disponível');

        $veiculos = $veiculos->get();

        // dd($veiculos);
        return response($veiculos, 200);
    }

    public function insertVeiculos(Request $request)
    {
        $lastCodVeiculo = DB::table('VEICULOS')->max('cod_veiculo');
        $newCodVeiculo = $lastCodVeiculo + 1;
        $veiculo = DB::table('VEICULOS')->insert([
            'cod_veiculo' => $newCodVeiculo,
            'modelo' => $request->input('modelo'),
            'placa' => $request->input('placa'),
            'ano' => $request->input('ano'),
            'capacidade' => $request->input('capacidade'),
            'dt_prox_manu' => $request->input('dataProxManutencao'),
            'dt_ultim_manu' => $request->input('dataUltManutencao'),
            'status' => 'disponivel',
            'id_tipo_veiculo' => $request->input('tipoVeiculo'),
        ]);

        return response()->json(['success' => true, 'message' => 'Veículo cadastrado com sucesso!'], 200);
    }
    public function deleteVeiculos(Request $request)
    {
        $placa = $request->input('placa');

        if (!$placa) {
            return response()->json(['success' => false, 'message' => 'Placa do veículo não fornecido'], 400);
        }


        $deleted = DB::table('VEICULOS')->where('placa', $placa)->delete();

        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Veículo excluído com sucesso!'], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Erro ao excluir veículo ou veículo não encontrado'], 400);
        }
    }
    public function editVeiculos(Request $request)
    {
        // dd($request->all());
        $veiculo = DB::table('VEICULOS')->where('cod_veiculo', $request->numVeiculo)->first();

        if (!$veiculo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Veículo não encontrado',
            ], 404);
        }

        DB::table('VEICULOS')
            ->where('cod_veiculo', $request->numVeiculo)
            ->update([
                'modelo' => $request->modelo,
                'ano' => $request->ano,
                'capacidade' => $request->capacidade,
                'placa' => $request->placa,
                // 'dt_prox_manu' => $request->dataProxManutencao,
                // 'dt_ultim_manu' => $request->dataUltManutencao,
                'id_tipo_veiculo' => $request->tipoVeiculo,
                'cod_motorista' => $request->motorista,
            ]);

        DB::table('EMPRESAS')
            ->where('cod_empresa', $veiculo->cod_empresa)
            ->update([
                'nome' => $request->empresa,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Veículo atualizado com sucesso',
        ], 200);
    }

    public function editStatusVeiculo(Request $request)
    {
        // dd($request->all());
        $veiculo = DB::table('VEICULOS')->where('placa', $request->placa)->first();

        if (!$veiculo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Veículo não encontrado',
            ], 404);
        }

        DB::table('VEICULOS')
            ->where('placa', $request->placa)
            ->update([
                'status' => $request->status,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status do veículo atualizado com sucesso',
        ], 200);
    }

    public function getMotoristas(Request $request)
    {
        $motoristas = DB::table('usuarios as u')
            ->join('tipos_usuario as t', 'u.id_tipo_usuario', '=', 't.id')
            ->select(
                'u.cod_usur',
                'u.nome',
                't.descricao'
            )
            ->where('t.descricao', 'Motorista')
            ->get();

        return response($motoristas, 200);
    }
}