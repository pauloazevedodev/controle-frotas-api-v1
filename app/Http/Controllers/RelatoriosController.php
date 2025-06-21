<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatoriosController extends Controller
{
    public function getRelatorioRotas(Request $request)
{
    $cod_veiculo = $request->query('cod_veiculo');

    $rotas = DB::table('ROTAS as r')
        ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
        ->join('log_scan_hodometro as l', 'r.cod_rota', '=', 'l.cod_rota')
        ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
        ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
        ->join('ROTA_INFO as ri', 'r.cod_rota', '=', 'ri.cod_rota')
        ->join('TIPOS_VEICULO as tv', 'v.id_tipo_veiculo', '=', 'tv.id')
        ->join('USUARIOS as u', 'r.cod_motorista', '=', 'u.cod_usur')
        ->select(
            'v.cod_veiculo',
            'v.modelo',
            'v.placa',
            'v.ano',
            'v.status as status_veiculo',
            'v.dt_ultim_manu',
            'v.dt_prox_manu',
            'tv.descricao as tipo_veiculo',
            'l.quilometragem',
            'r.cod_rota',
            'r.status as status_rota',
            'r.desc_status as desc_status_rota',
            'r.obs_rota',
            'r.servico_exec',
            'ri.km_percorrido',
            'ri.num_paradas',
            'u.cpf',
            'u.email',
            'u.nome',
            'p.cep_partida',
            'p.numero_partida',
            'p.descricao_partida',
            'p.complemento_partida',
            'p.rua_partida',
            'p.bairro_partida',
            'p.cidade_partida',
            'p.estado_partida',
            'p.latitude_partida',
            'p.longitude_partida',
            'p.data_hora_partida',
            'c.cep_chegada',
            'c.numero_chegada',
            'c.descricao_chegada',
            'c.complemento_chegada',
            'c.rua_chegada',
            'c.bairro_chegada',
            'c.cidade_chegada',
            'c.estado_chegada',
            'c.latitude_chegada',
            'c.longitude_chegada',
            'c.data_hora_chegada'
        )
        ->where('v.cod_veiculo', $cod_veiculo)
        ->get();

    foreach ($rotas as $rota) {
        $paradas = DB::table('PARADAS as pr')
            ->where('pr.cod_rota', $rota->cod_rota)
            ->select(
                'pr.cep_parada',
                'pr.numero_parada',
                'pr.descricao_parada',
                'pr.complemento_parada',
                'pr.rua_parada',
                'pr.bairro_parada',
                'pr.cidade_parada',
                'pr.estado_parada',
                'pr.latitude_parada',
                'pr.longitude_parada'
            )
            ->get();

        $rota->paradas = $paradas;
    }
    return response()->json($rotas, 200);
}


}