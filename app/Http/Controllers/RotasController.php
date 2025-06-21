<?php

namespace App\Http\Controllers;

use App\Models\Log;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RotasController extends Controller
{
    public function buscarEndereco($cep)
    {
        // dd($cep);
        $response = Http::withoutVerifying()->get("https://viacep.com.br/ws/{$cep}/json/");
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'CEP inválido ou erro na requisição'], 400);
        }
    }

    public function getEnderecoByLatLong($lat, $lng)
{
    $apiKey = 'AIzaSyDBwpZs8ef-S4luuIvphLWNSSs5XCga_kc';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$lat,$lng&key=$apiKey";
    $response = Http::get($url);
    if ($response->ok()) {
        $results = $response->json()['results'];
        if (count($results) > 0) {
            return $results[0]['address_components'];
        }
    }
    return null;
}

    
private function obterUf($address)
{
    foreach ($address as $item) {
        if (in_array('administrative_area_level_1', $item['types'])) {
            return $item['short_name'];
        }
    }
    return null;
}

public function insertPartida(Request $request)
{
    $infoPartida = $request->all();
    
    $latitude  = $infoPartida['latitude'];
    $longitude = $infoPartida['longitude'];
    $cod_rota  = $infoPartida['routeInfo'][0]['cod_rota'];
    $cod_usur  = $infoPartida['codUsur'];

    $address = $this->getEnderecoByLatLong($latitude, $longitude);
    if (!$address) {
        return response()->json(['error' => 'Endereço não encontrado.'], 400);
    }

    $uf = $this->obterUf($address);

    $addressMap = collect($address)->mapWithKeys(function ($item) {
        return [$item['types'][0] => $item['long_name']];
    });

    DB::table('ROTAS')
    ->where('cod_rota', $cod_rota)
    ->update([
        'cod_partida' => $cod_rota
    ]);

    DB::table('PARTIDAS')->insert([
        'cod_rota'            => $cod_rota,
        'cod_partida'         => $cod_rota,
        'cep_partida'         => $addressMap['postal_code'] ?? null,
        'rua_partida'         => $addressMap['route'] ?? null,
        'numero_partida'      => $addressMap['street_number'] ?? null,
        'bairro_partida'      => $addressMap['political'] ?? null,
        'cidade_partida'      => $addressMap['administrative_area_level_2'] ?? null,
        'estado_partida'      => $uf, 
        'latitude_partida'    => $latitude,
        'longitude_partida'   => $longitude,
    ]);

    return response()->json(['success' => true , 'message' => 'Partida cadastrada com sucesso!'], 200);
}

private function buscarLatLongGoogle($enderecoCompleto)
{
    $apiKey = 'AIzaSyDBwpZs8ef-S4luuIvphLWNSSs5XCga_kc';
    $url = "https://maps.googleapis.com/maps/api/geocode/json";

    $response = Http::get($url, [
        'address' => $enderecoCompleto,
        'key' => $apiKey,
        'region' => 'br', 
    ]);

    if ($response->failed()) {
        return [null, null];
    }

    $json = $response->json();
    if (!empty($json['results'][0]['geometry']['location'])) {
        $lat = $json['results'][0]['geometry']['location']['lat'];
        $lng = $json['results'][0]['geometry']['location']['lng'];
        return [$lat, $lng];
    }

    return [null, null];
}


public function insertRotas(Request $request)
{
    $info = $request->all();
    $cod_veiculo = $request->input('veiculo.cod_veiculo');

    $codRota = DB::table('ROTAS')
        // ->where('cod_veiculo', $cod_veiculo)
        ->count() + 1;

    // $enderecoPartida      = $request->input('enderecoPartida');
    // $cepPartida           = $request->input('cepPartida');
    // $numeroPartida        = $request->input('numeroPartida');
    // $complementoPartida   = $request->input('complementoPartida');

    // $enderecoCompletoPartida = "{$enderecoPartida['rua']}, {$numeroPartida}, {$enderecoPartida['bairro']}, {$enderecoPartida['cidade']} - {$enderecoPartida['estado']}, {$cepPartida}";
    // if ($complementoPartida) {
    //     $enderecoCompletoPartida .= ", $complementoPartida";
    // }

    // [$latPartida, $lngPartida] = $this->buscarLatLongGoogle($enderecoCompletoPartida);

    $enderecoChegada      = $request->input('enderecoChegada');
    $cepChegada           = $request->input('cepChegada');
    $numeroChegada        = $request->input('numeroChegada');
    $complementoChegada   = $request->input('complementoChegada');

    $enderecoCompletoChegada = "{$enderecoChegada['rua']}, {$numeroChegada}, {$enderecoChegada['bairro']}, {$enderecoChegada['cidade']} - {$enderecoChegada['estado']}, {$cepChegada}";
    if ($complementoChegada) {
        $enderecoCompletoChegada .= ", $complementoChegada";
    }

    [$latChegada, $lngChegada] = $this->buscarLatLongGoogle($enderecoCompletoChegada);

    $paradas = $request->input('paradas', []);
    $paradasData = [];
    $codParada = 1;

    foreach ($paradas as $parada) {
        $end = $parada['endereco'];
        $cep   = $parada['cep'] ?? '';
        $numero = $parada['numero'] ?? '';
        $compl = $parada['complemento'] ?? null;

        $enderecoCompletoParada = "{$end['rua']}, {$numero}, {$end['bairro']}, {$end['cidade']} - {$end['estado']}, {$cep}";
        if ($compl) {
            $enderecoCompletoParada .= ", $compl";
        }

        [$lat, $lng] = $this->buscarLatLongGoogle($enderecoCompletoParada);

        $paradasData[] = [
            'COD_ROTA'           => $codRota,
            'COD_PARADA'         => $codParada,
            'CEP_PARADA'         => $cep,
            'NUMERO_PARADA'      => $numero,
            'DESCRICAO_PARADA'   => $parada['descricao'] ?? null,
            'COMPLEMENTO_PARADA' => $compl,
            'RUA_PARADA'         => $end['rua'],
            'BAIRRO_PARADA'      => $end['bairro'],
            'CIDADE_PARADA'      => $end['cidade'],
            'ESTADO_PARADA'      => $end['estado'],
            'LATITUDE_PARADA'    => $lat,
            'LONGITUDE_PARADA'   => $lng,
        ];
        $codParada++;
    }

    DB::table('ROTAS')->insert([
        'COD_ROTA'      => $codRota,
        'cod_veiculo'   => $cod_veiculo,
        'cod_parada'    => 1,
        'cod_chegada'   => $codRota,
        'cod_partida'   => $codRota,
        'cod_motorista' => $request->input('motorista'),
    ]);

    // DB::table('PARTIDAS')->insert([
    //     'cod_rota'            => $codRota,
    //     'cod_partida'         => $codRota,
    //     'cep_partida'         => $cepPartida,
    //     'numero_partida'      => $numeroPartida,
    //     'descricao_partida'   => $request->input('descricaoPartida'),
    //     'complemento_partida' => $complementoPartida,
    //     'rua_partida'         => $enderecoPartida['rua'],
    //     'bairro_partida'      => $enderecoPartida['bairro'],
    //     'cidade_partida'      => $enderecoPartida['cidade'],
    //     'estado_partida'      => $enderecoPartida['estado'],
    //     'latitude_partida'    => $latPartida,
    //     'longitude_partida'   => $lngPartida,
    //     'data_hora_partida'   => now(),
    // ]);

    DB::table('CHEGADAS')->insert([
        'cod_rota'             => $codRota,
        'cod_chegada'          => $codRota,
        'cep_chegada'          => $cepChegada,
        'numero_chegada'       => $numeroChegada,
        'descricao_chegada'    => $request->input('descricaoChegada'),
        'complemento_chegada'  => $complementoChegada,
        'rua_chegada'          => $enderecoChegada['rua'],
        'bairro_chegada'       => $enderecoChegada['bairro'],
        'cidade_chegada'       => $enderecoChegada['cidade'],
        'estado_chegada'       => $enderecoChegada['estado'],
        'latitude_chegada'     => $latChegada,
        'longitude_chegada'    => $lngChegada,
        // 'data_hora_chegada'    => now()->addHours(2),
    ]);

    if (!empty($paradasData)) {
        DB::table('PARADAS')->insert($paradasData);
    }

    return response()->json([
        'success' => true,
        'message' => 'Rota e paradas cadastradas com sucesso!'
    ], 200);
}


public function updateRotas(Request $request)
{
    $info = $request->all();

    if (empty($info['cod_rota'])) {
        return response()->json(['success' => false, 'message' => 'Código da Rota é obrigatório.'], 400);
    }

    $cod_rota = $info['cod_rota'];
    $cod_motorista = $info['cod_motorista'] ?? null;
    $cod_veiculo = $info['cod_veiculo'] ?? null;

    DB::beginTransaction(); 

    try {
        DB::table('ROTAS')
            ->where('COD_ROTA', $cod_rota)
            ->update([
                'cod_motorista' => $cod_motorista,
                'cod_veiculo'   => $cod_veiculo,
            ]);

        if (isset($info['chegada'])) {
            $chegada = $info['chegada'];
            $enderecoCompletoChegada = "{$chegada['rua']}, {$chegada['numero']}, {$chegada['bairro']}, {$chegada['cidade']} - {$chegada['estado']}, {$chegada['cep']}";
            if (!empty($chegada['complemento'])) {
                $enderecoCompletoChegada .= ", {$chegada['complemento']}";
            }

            [$latChegada, $lngChegada] = $this->buscarLatLongGoogle($enderecoCompletoChegada);

            DB::table('CHEGADAS')
                ->where('cod_rota', $cod_rota)
                ->update([
                    'cep_chegada'         => $chegada['cep'] ?? null,
                    'numero_chegada'      => $chegada['numero'] ?? null,
                    'descricao_chegada'   => $chegada['descricao'] ?? null,
                    'complemento_chegada' => $chegada['complemento'] ?? null,
                    'rua_chegada'         => $chegada['rua'] ?? null,
                    'bairro_chegada'      => $chegada['bairro'] ?? null,
                    'cidade_chegada'      => $chegada['cidade'] ?? null,
                    'estado_chegada'      => $chegada['estado'] ?? null,
                    'latitude_chegada'    => $latChegada,
                    'longitude_chegada'   => $lngChegada,
                ]);
        }

        $paradasInput = $info['paradas'] ?? [];
        $codParadasInput = []; 

        $paradasExistentesIds = DB::table('PARADAS')
                                  ->where('cod_rota', $cod_rota)
                                  ->pluck('COD_PARADA') 
                                  ->toArray(); 

        foreach ($paradasInput as $index => $parada) {
            $end = $parada;
            $cep   = $parada['cep'] ?? null;
            $numero = $parada['numero'] ?? null;
            $compl = $parada['complemento'] ?? null;

            $enderecoCompletoParada = "{$end['rua']}, {$numero}, {$end['bairro']}, {$end['cidade']} - {$end['estado']}, {$cep}";
            if ($compl) {
                $enderecoCompletoParada .= ", $compl";
            }

            [$lat, $lng] = $this->buscarLatLongGoogle($enderecoCompletoParada);

            $paradaData = [
                'COD_ROTA'           => $cod_rota, 
                'CEP_PARADA'         => $cep,
                'NUMERO_PARADA'      => $numero,
                'DESCRICAO_PARADA'   => $parada['descricao'] ?? null,
                'COMPLEMENTO_PARADA' => $compl,
                'RUA_PARADA'         => $end['rua'] ?? null,
                'BAIRRO_PARADA'      => $end['bairro'] ?? null,
                'CIDADE_PARADA'      => $end['cidade'] ?? null,
                'ESTADO_PARADA'      => $end['estado'] ?? null,
                'LATITUDE_PARADA'    => $lat,
                'LONGITUDE_PARADA'   => $lng,
            ];

            if (isset($parada['cod_parada'])) {
                $cod_parada_atual = $parada['cod_parada'];
                $codParadasInput[] = $cod_parada_atual; 

                DB::table('PARADAS')
                    ->where('cod_rota', $cod_rota)
                    ->where('COD_PARADA', $cod_parada_atual)
                    ->update($paradaData); 

            } else {
                $nextCodParada = (DB::table('PARADAS')->where('cod_rota', $cod_rota)->max('COD_PARADA') ?? 0) + 1;

                $paradaData['COD_PARADA'] = $nextCodParada; 

                DB::table('PARADAS')->insert($paradaData);

                
            }
        } 

        $paradasParaDeletarIds = array_diff($paradasExistentesIds, $codParadasInput);

        if (!empty($paradasParaDeletarIds)) {
            DB::table('PARADAS')
                ->where('cod_rota', $cod_rota)
                ->whereIn('COD_PARADA', $paradasParaDeletarIds)
                ->delete();
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Rota atualizada com sucesso!'
        ], 200);

    } catch (Throwable $e) {
        DB::rollBack();

        Log::error("Erro ao atualizar rota {$cod_rota}: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Ocorreu um erro ao atualizar a rota.'
        ], 500); 
    }
}

    public function getRotas(Request $request)
    {
        // dd($request->all());
        $cod_veiculo = $request->query('cod_veiculo');

        $rotasRaw = DB::table('ROTAS as r')
            ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
            ->leftjoin('PARTIDAS as p', 'r.cod_partida', '=', 'p.cod_partida')
            ->join('CHEGADAS as c', 'r.cod_chegada', '=', 'c.cod_chegada')
            ->leftjoin('PARADAS as pr', 'r.cod_rota', '=', 'pr.cod_rota')
            ->leftJoin('ROTA_INFO as ri', 'r.cod_rota', '=', 'ri.cod_rota')
            ->join('USUARIOS as u', 'r.cod_motorista', '=', 'u.cod_usur')
            ->select(
                'r.cod_rota',
                'r.cod_veiculo',
                'r.status',
                'r.desc_status',
                'p.cep_partida',
                'p.numero_partida',
                'p.descricao_partida',
                'p.complemento_partida',
                'p.rua_partida',
                'p.bairro_partida',
                'p.cidade_partida',
                'p.estado_partida',
                'p.data_hora_partida',
                'c.cep_chegada',
                'c.numero_chegada',
                'c.descricao_chegada',
                'c.complemento_chegada',
                'c.rua_chegada',
                'c.bairro_chegada',
                'c.cidade_chegada',
                'c.estado_chegada',
                'c.data_hora_chegada',
                'pr.cod_parada',
                'pr.cep_parada as cepParada',
                'pr.numero_parada as numeroParada',
                'pr.descricao_parada as descricaoParada',
                'pr.complemento_parada as complementoParada',
                'pr.rua_parada as ruaParada',
                'pr.bairro_parada as bairroParada',
                'pr.cidade_parada as cidadeParada',
                'pr.estado_parada as estadoParada',
                'ri.km_percorrido',
                'u.nome as motorista_nome',
                'u.cod_usur'
            )
            ->when($cod_veiculo, function ($query, $cod_veiculo) {
                $query->where('v.cod_veiculo', $cod_veiculo);
            })
            ->get();

        $rotas = [];
        foreach ($rotasRaw as $row) {
            $codRota = $row->cod_rota;

            if (!isset($rotas[$codRota])) {
                $rotas[$codRota] = [
                    'cod_rota' => $row->cod_rota,
                    'cod_veiculo' => $row->cod_veiculo,
                    'status' => $row->status,
                    'desc_status' => $row->desc_status,
                    'km_percorrido' => $row->km_percorrido,
                    'motorista' => [
                        'cod_motorista' => $row->cod_usur,
                        'nome' => $row->motorista_nome,
                    ],
                    'partida' => [
                        'cep' => $row->cep_partida,
                        'numero' => $row->numero_partida,
                        'descricao' => $row->descricao_partida,
                        'complemento' => $row->complemento_partida,
                        'rua' => $row->rua_partida,
                        'bairro' => $row->bairro_partida,
                        'cidade' => $row->cidade_partida,
                        'estado' => $row->estado_partida,
                        'data_hora' => $row->data_hora_partida,
                    ],
                    'chegada' => [
                        'cep' => $row->cep_chegada,
                        'numero' => $row->numero_chegada,
                        'descricao' => $row->descricao_chegada,
                        'complemento' => $row->complemento_chegada,
                        'rua' => $row->rua_chegada,
                        'bairro' => $row->bairro_chegada,
                        'cidade' => $row->cidade_chegada,
                        'estado' => $row->estado_chegada,
                        'data_hora' => $row->data_hora_chegada,
                    ],
                    'paradas' => [],
                ];
            }

            if ($row->cod_parada) {
                $rotas[$codRota]['paradas'][] = [
                    'cod_parada' => $row->cod_parada,
                    'cep' => $row->cepParada,
                    'numero' => $row->numeroParada,
                    'descricao' => $row->descricaoParada,
                    'complemento' => $row->complementoParada,
                    'rua' => $row->ruaParada,
                    'bairro' => $row->bairroParada,
                    'cidade' => $row->cidadeParada,
                    'estado' => $row->estadoParada,
                ];
            }
        }

        $rotas = array_values($rotas);

        return response()->json([
            'success' => true,
            'message' => 'Rota e paradas cadastradas com sucesso!',
            'rotas' => $rotas,
        ], 200);
    }
    public function getRotasMobile(Request $request)
    {
        // dd($request->all());
        $info = $request->all();
        $cod_rota  = $info['routeInfo'][0]['cod_rota'];

        $rotasRaw = DB::table('ROTAS as r')
    ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
    ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
    ->leftjoin('PARADAS as pr', 'r.cod_rota', '=', 'pr.cod_rota')
    ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
    ->select(
        'p.latitude_partida',
        'p.longitude_partida',
        'p.rua_partida',
        'p.cidade_partida',
        'p.estado_partida',
        'p.numero_partida',
        'c.latitude_chegada',
        'c.longitude_chegada',
        'c.rua_chegada',
        'c.cidade_chegada',
        'c.estado_chegada',
        'c.numero_chegada',
        'pr.cod_parada',
        'pr.rua_parada',
        'pr.cidade_parada',
        'pr.estado_parada',
        'pr.numero_parada',
        'pr.latitude_parada',
        'pr.longitude_parada',
        'r.cod_rota',
        'r.cod_veiculo',
        'r.status',
        'r.desc_status',
        'v.modelo',
        'v.placa',
        'v.capacidade',
        'v.ano'
    )
    ->where('r.cod_rota', $cod_rota)
    ->get();

$rotaBase = $rotasRaw->first();

$rota = [
    'cod_rota' => $rotaBase->cod_rota,
    'partida' => [
        'latitude' => $rotaBase->latitude_partida,
        'longitude' => $rotaBase->longitude_partida,
        'rua' => $rotaBase->rua_partida,
        'numero' => $rotaBase->numero_partida,
        'cidade' => $rotaBase->cidade_partida,
        'estado' => $rotaBase->estado_partida,
    ],
    'chegada' => [
        'latitude' => $rotaBase->latitude_chegada,
        'longitude' => $rotaBase->longitude_chegada,
        'rua' => $rotaBase->rua_chegada,
        'numero' => $rotaBase->numero_chegada,
        'cidade' => $rotaBase->cidade_chegada,
        'estado' => $rotaBase->estado_chegada,
    ],
    'veiculo' => [
        'cod_veiculo' => $rotaBase->cod_veiculo,
        'modelo' => $rotaBase->modelo,
        'placa' => $rotaBase->placa,
        'capacidade' => $rotaBase->capacidade,
        'ano' => $rotaBase->ano,
    ],
    'status' => $rotaBase->status,
    'desc_status' => $rotaBase->desc_status,
    'paradas' => [],
];

foreach ($rotasRaw as $item) {
    if ($item->cod_parada !== null) {
        $rota['paradas'][] = [
            'cod_parada' => $item->cod_parada,
            'rua' => $item->rua_parada,
            'numero' => $item->numero_parada,
            'cidade' => $item->cidade_parada,
            'estado' => $item->estado_parada,
            'latitude' => $item->latitude_parada,
            'longitude' => $item->longitude_parada,
        ];
    }
}
return response()->json([
    'success' => true,
    'message' => 'Rota carregada com sucesso!',
    'rota' => $rota,
], 200);
    }


    public function getObsRotas(Request $request)
    {
        // dd($request->all());
        $cod_rota = $request->query('cod_rota');
        $cod_veiculo = $request->query('cod_veiculo');
        $rotas = DB::table('rotas')
            ->select(
                'cod_rota',
                'obs_adicional'
            )
            ->where('cod_veiculo', $cod_veiculo)
            ->where('cod_rota', $cod_rota)
            ->get();

        return response($rotas, 200);
    }
    public function getStatusRotas(Request $request)
    {
        // dd($request->all());
        $cod_rota = $request->query('cod_rota');
        $cod_veiculo = $request->query('cod_veiculo');
        $rotas = DB::table('rotas')
            ->select(
                'status',
                'desc_status',
            )
            ->where('cod_veiculo', $cod_veiculo)
            ->where('cod_rota', $cod_rota)
            ->get();

        return response($rotas, 200);
    }
    public function editStatusRota(Request $request)
    {
        // dd($request->all());
        $codRota = $request->input('codRota');
        $rota = DB::table('ROTAS')->where('cod_veiculo', $request->cod_veiculo)->first();

        if (!$rota) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rota não encontrada',
            ], 404);
        }

        DB::table('ROTAS')
            ->where('cod_veiculo', $request->cod_veiculo)
            ->where('cod_rota', $codRota)
            ->update([
                'status' => $request->status,
                'desc_status' => $request->desc
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status da rota atualizado com sucesso',
        ], 200);
    }
    public function updateObsRotas(Request $request)
    {
        $cod_veiculo = $request->input('cod_veiculo');
        $cod_rota = $request->input('cod_rota');
        $rota = DB::table('ROTAS')->where('cod_veiculo', $cod_veiculo)->first();

        if (!$rota) {
            return response()->json(['success' => false, 'message' => `Rota não encontrada para o código de rota $cod_rota `], 404);
        }

        DB::table('ROTAS')
            ->where('cod_veiculo', $cod_veiculo)
            ->where('cod_rota', $cod_rota)
            ->update([
                'obs_adicional' => $request->input('observacoesAdicionais'),
            ]);

        return response()->json(['success' => true, 'message' => 'Observações atualizadas com sucesso!'], 200);
    }
    public function linkMotorista(Request $request)
    {
        $info = $request->all();
        // dd($info);

        $cod_motorista = $info['codUsur'];
        $cod_rota = $info['routeInfo'][0]['cod_rota'];        
        
        $rota = DB::table('ROTAS')->where('cod_rota', $cod_rota)->first();


        if($cod_motorista != $rota->cod_motorista){
            return response()->json(['success' => false, 'message' => 'Você não está cadastrado nessa rota.'], 400);
        }
        if (!$rota) {
            return response()->json(['success' => false, 'message' => `Rota não encontrada para o código de rota $cod_rota `], 404);
        }

        DB::table('ROTAS')
            ->where('cod_rota', $cod_rota)
            ->update([
                'scan' => 'S',
            ]);

        return response()->json(['success' => true, 'message' => 'Motorista vinculado ao veiculo e a rota com sucesso!'], 200);
    }
    public function insertHodometro(Request $request)
    {
        $info = $request->all();
        // dd($info);

        $cod_motorista = $info['codUsur'];
        $cod_rota = $info['routeInfo'][0]['cod_rota'];     
        $quilometragem = $info['quilometragem'];
        $imagemBase64 = $info['imagem'];

        $imageData = base64_decode($imagemBase64);

        $filename = 'hodometro_' . now()->format('YmdHis') . '_' . $request->input('codUsur') . '.jpg';

        $folder = storage_path('app/public/hodometros');
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        $path = $folder . '/' . $filename;

        file_put_contents($path, $imageData);

        DB::table('log_scan_hodometro')->insert([
            'quilometragem' => $quilometragem,
            'imagem_path' => 'hodometros/' . $filename,
            'cod_usur' => $cod_motorista,
            'cod_rota' => $cod_rota,
            'data' => now()->format('Y-m-d H:i:s')
        ]);


        return response()->json(['success' => true, 'message' => 'Quilometragem registrada com sucesso!'], 200);
    }
    

    public function getDirections(Request $request)
{  
    $waypoints = collect($request->stops)
            ->map(fn($p) => "{$p['latitude']},{$p['longitude']}")
            ->join('|');

        $origin = "{$request->start['latitude']},{$request->start['longitude']}";
        $destination = "{$request->end['latitude']},{$request->end['longitude']}";

        $client = new Client();
        $key = 'AIzaSyDBwpZs8ef-S4luuIvphLWNSSs5XCga_kc';
        $url = "https://maps.googleapis.com/maps/api/directions/json?origin=$origin"
             . "&destination=$destination&key=$key&waypoints=$waypoints&language=pt-BR&mode=driving&optimizeWaypoints=false";

        $response = $client->get($url);
        return response()->json(json_decode($response->getBody(), true));
    }

    public function insertHoraPartida(Request $request)
{
    $info = $request->all();
    // dd($info);

    $cod_rota = $info['cod_rota'];
    $hora_partida = $info['hora_partida'];

    DB::table('PARTIDAS')
        ->where('cod_rota', $cod_rota)
        ->update([
            'data_hora_partida' => $hora_partida,
        ]);

    $cod_veiculo = DB::table('ROTAS')
        ->where('cod_rota', $info['cod_rota'])
        ->value('cod_veiculo');  

    if ($cod_veiculo) {
        DB::table('VEICULOS')
            ->where('cod_veiculo', $cod_veiculo)
            ->update([
                'status' => 'Indisponivel',
                'obs_status' => 'Veículo em Trânsito',
            ]);
    } else {
        return response()->json(['success' => false, 'message' => 'Veículo não encontrado para a rota especificada.'], 404);
    }

    return response()->json(['success' => true, 'message' => 'Hora de partida atualizada com sucesso!'], 200);
}
    public function insertHoraChegada(Request $request)
    {
        $info = $request->all();

        $cod_rota = $info['cod_rota'];
        $hora_chegada = $info['hora_chegada'];

       

        return response()->json(['success' => true, 'message' => 'Hora de chegada atualizada com sucesso!'], 200);
    }
    public function getDuracaoRota(Request $request)
{
    $info = $request->all();
    $cod_rota = $info['cod_rota'];

    $horas = DB::table('ROTAS as r')
        ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
        ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
        ->select(
            'p.data_hora_partida',
            'c.data_hora_chegada',
            'r.cod_rota'
        )
        ->where('r.cod_rota', $cod_rota)
        ->get();

    if ($horas->isNotEmpty()) {
        $hora = $horas->first();

        $dataHoraPartida = new DateTime($hora->data_hora_partida);
        $dataHoraChegada = new DateTime($hora->data_hora_chegada);

        $intervalo = $dataHoraPartida->diff($dataHoraChegada);

        $duracao = $intervalo->format('%H:%I:%S');

        return response()->json(['success' => true, 'duracao' => $duracao], 200);
    }


    return response()->json(['success' => false, 'message' => 'Rota não encontrada'], 404);
}

public function insertRouteInfo(Request $request)
{
    $info = $request->all();
    DB::table('ROTA_INFO')->insert([
        'cod_rota' => $info['cod_rota'],
        'partida_lat' => $info['partida_lat'],
        'partida_lng' => $info['partida_lng'],
        'chegada_lat' => $info['chegada_lat'],
        'chegada_lng' => $info['chegada_lng'],
        'km_percorrido' => $info['km_percorrido'],
        'num_paradas' => $info['num_paradas'],
    ]);

    DB::table('CHEGADAS')
    ->where('cod_rota', $info['cod_rota'])
    ->update([
        'data_hora_chegada' => $info['data_hora_fim'],
    ]);

    $cod_veiculo = DB::table('ROTAS')
        ->where('cod_rota', $info['cod_rota'])
        ->value('cod_veiculo');  

    if ($cod_veiculo) {
    DB::table('VEICULOS')
    ->where('cod_veiculo', $cod_veiculo)
    ->update([
        'status' => 'Disponível',
        'obs_status' => ''
    ]);
    } else {
        return response()->json(['success' => false, 'message' => 'Veículo não encontrado para a rota especificada.'], 404);
    }


    return response()->json(['success' => true, 'message' => 'Informações da rota salvas com sucesso!'], 200);
}

public function insertRouteSteps(Request $request)
{
    $info = $request->all();

    $stepsData = array_map(function($step) use ($info) {
        return [
            'cod_rota' => $info['cod_rota'],
            'step_index' => $step['step_index'],
            'start_lat' => $step['start_lat'],
            'start_lng' => $step['start_lng'],
            'end_lat' => $step['end_lat'],
            'end_lng' => $step['end_lng'],
            'instruction' => $step['instruction'],
            'distance' => $step['distance'],
        ];
    }, $info['steps']);

    DB::table('ROTA_STEPS')->insert($stepsData);

    return response()->json(['success' => true, 'message' => 'Steps da rota salvos com sucesso!'], 200);
}
public function fetchViagens(Request $request)
{
    $info = $request->all();
    $codMotorista = $info['codMotorista'];
    $idTipoUsuario = $info['idTipoUsuario'];

    $viagens = DB::table('ROTAS as r')
    ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
    ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
    ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
    ->join('ROTA_INFO as ri', 'r.cod_rota', '=', 'ri.cod_rota')
    ->select(
        'r.cod_rota',
        'r.status as status_rota',
        'r.cod_motorista',
        'v.modelo',
        'v.placa',
        'p.data_hora_partida',
        'c.data_hora_chegada',
        'ri.km_percorrido',
        'ri.num_paradas',
    )
    ->when($idTipoUsuario == 2, function ($query) use ($codMotorista) {
        return $query->where('r.cod_motorista', $codMotorista);
    })
    ->distinct()
    ->get();


    return response()->json(['success' => true, 'message' => 'Viagens carregadas com sucesso!', 'viagens' => $viagens], 200);
}
public function fetchMaisInfoViagens(Request $request)
{
    $info = $request->all();
    $codMotorista = $info['codMotorista'];
    $idTipoUsuario = $info['idTipoUsuario'];

    $viagens = DB::table('ROTAS as r')
    ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
    ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
    ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
    ->join('ROTA_INFO as ri', 'r.cod_rota', '=', 'ri.cod_rota')
    ->select(
        'r.cod_rota',
        'r.status as status_rota',
        'r.cod_motorista',
        'v.modelo',
        'v.placa',
        'p.data_hora_partida',
        'c.data_hora_chegada',
        'ri.km_percorrido',
        'ri.num_paradas',
    )
    ->when($idTipoUsuario == 2, function ($query) use ($codMotorista) {
        return $query->where('r.cod_motorista', $codMotorista);
    })
    ->distinct()
    ->get();


    return response()->json(['success' => true, 'message' => 'Viagens carregadas com sucesso!', 'viagens' => $viagens], 200);
}
public function editStatusRotaMobile(Request $request)
{
    $info = $request->all();
    $codRota = $info['codRota'];
    $codSuperv = $info['codSuperv'];
    $status = $info['status'];
    $descricao = $info['motivo'];
    
    DB::table('ROTAS')
            ->where('cod_rota', $codRota)
            ->update([
                'status' => $status,
                'desc_status' => $descricao,
                'cod_superv' => $codSuperv
            ]);


    return response()->json(['success' => true, 'message' => 'Status atualizado com sucesso!' ], 200);
}
    public function getStepsRota(Request $request)
{
    // dd($request->all());
    $info = $request->all();
    $cod_rota  = $info['codRota'];

    

    $rotasRaw = DB::table('ROTAS as r')
        ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
        ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
        ->leftjoin('PARADAS as pr', 'r.cod_rota', '=', 'pr.cod_rota')
        ->join('VEICULOS as v', 'r.cod_veiculo', '=', 'v.cod_veiculo')
        ->join('ROTA_STEPS as rs', 'r.cod_rota', '=', 'rs.cod_rota')
        ->join('USUARIOS as u', 'r.cod_motorista', '=', 'u.cod_usur')
        ->leftJoin('USUARIOS as u2', 'r.cod_superv', '=', 'u2.cod_usur')
        ->select(
            'p.latitude_partida',
            'p.longitude_partida',
            'p.rua_partida',
            'p.cidade_partida',
            'p.estado_partida',
            'p.numero_partida',
            'c.latitude_chegada',
            'c.longitude_chegada',
            'c.rua_chegada',
            'c.cidade_chegada',
            'c.estado_chegada',
            'c.numero_chegada',
            'pr.cod_parada',
            'pr.rua_parada',
            'pr.cidade_parada',
            'pr.estado_parada',
            'pr.numero_parada',
            'pr.latitude_parada',
            'pr.longitude_parada',
            'r.cod_rota',
            'r.cod_veiculo',
            'r.status',
            'r.desc_status',
            'v.modelo',
            'v.placa',
            'v.capacidade',
            'v.ano',
            'rs.step_index',
            'rs.start_lat',
            'rs.start_lng',
            'rs.end_lat',
            'rs.end_lng',    
            'rs.instruction',    
            'rs.distance',
            'u.nome as nome_motorista',
            'u.cod_usur as codigo_motorista', 
            'u2.nome as nome_supervisor',
            'u2.cod_usur as codigo_supervisor'    
        )
        ->where('r.cod_rota', $cod_rota)
        ->get();

        $rotaBase = $rotasRaw->first();

        $rota = [
        'cod_rota' => $rotaBase->cod_rota,
        'partida' => [
            'latitude' => $rotaBase->latitude_partida,
            'longitude' => $rotaBase->longitude_partida,
            'rua' => $rotaBase->rua_partida,
            'numero' => $rotaBase->numero_partida,
            'cidade' => $rotaBase->cidade_partida,
            'estado' => $rotaBase->estado_partida,
        ],
        'chegada' => [
            'latitude' => $rotaBase->latitude_chegada,
            'longitude' => $rotaBase->longitude_chegada,
            'rua' => $rotaBase->rua_chegada,
            'numero' => $rotaBase->numero_chegada,
            'cidade' => $rotaBase->cidade_chegada,
            'estado' => $rotaBase->estado_chegada,
        ],
        'veiculo' => [
            'cod_veiculo' => $rotaBase->cod_veiculo,
            'modelo' => $rotaBase->modelo,
            'placa' => $rotaBase->placa,
            'capacidade' => $rotaBase->capacidade,
            'ano' => $rotaBase->ano,
        ],
        'motorista' => [
            'nome_motorista' => $rotaBase->nome_motorista,
            'codigo_motorista' => $rotaBase->codigo_motorista
        ],
        'supervisor' => [
            'nome_supervisor' => $rotaBase->nome_supervisor,
            'codigo_supervisor' => $rotaBase->codigo_supervisor
        ],
        'status' => $rotaBase->status,
        'desc_status' => $rotaBase->desc_status,
        'paradas' => [],
        ];

        foreach ($rotasRaw as $item) {
        if ($item->cod_parada !== null) {
            $rota['paradas'][] = [
                'cod_parada' => $item->cod_parada,
                'rua' => $item->rua_parada,
                'numero' => $item->numero_parada,
                'cidade' => $item->cidade_parada,
                'estado' => $item->estado_parada,
                'latitude' => $item->latitude_parada,
                'longitude' => $item->longitude_parada,
            ];
        }
        foreach ($rotasRaw as $item) {
        if ($item->step_index !== null) {
            $rota['steps'][] = [
                'step_index' => $item->step_index,
                'start_lat' => $item->start_lat,
                'start_lng' => $item->start_lng,
                'end_lat' => $item->end_lat,
                'end_lng' => $item->end_lng,
                'instruction' => $item->instruction,
                'distance' => $item->distance,
            ];
        }
        }

        $horas = DB::table('ROTAS as r')
        ->join('PARTIDAS as p', 'r.cod_rota', '=', 'p.cod_rota')
        ->join('CHEGADAS as c', 'r.cod_rota', '=', 'c.cod_rota')
        ->select(
            'p.data_hora_partida',
            'c.data_hora_chegada'
        )
        ->where('r.cod_rota', $cod_rota)
        ->first();

    if ($horas) {
        $dataHoraPartida = new DateTime($horas->data_hora_partida);
        $dataHoraChegada = new DateTime($horas->data_hora_chegada);

        $intervalo = $dataHoraPartida->diff($dataHoraChegada);
        $duracao = $intervalo->format('%H:%I:%S');

        $rota['duracao'] = $duracao;
    } else {
        $rota['duracao'] = null;
    }
        return response()->json([
            'success' => true,
            'message' => 'Rota carregada com sucesso!',
            'rota' => $rota,
        ], 200);
        }
    }
}