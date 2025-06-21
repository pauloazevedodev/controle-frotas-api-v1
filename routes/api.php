<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VeiculoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AuthController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('auth/login', 'App\Http\Controllers\AuthController@login');

// Route::middleware(['jwt.verify'])->group(function () {
    Route::post('termo/atualizar', 'App\Http\Controllers\AuthController@updateTermo');

    Route::post('insert/veiculos', 'App\Http\Controllers\VeiculoController@insertVeiculos');
    Route::get('veiculos', 'App\Http\Controllers\VeiculoController@getVeiculos');
    Route::post('edit/veiculos', 'App\Http\Controllers\VeiculoController@editVeiculos');
    Route::post('edit/status/veiculo', 'App\Http\Controllers\VeiculoController@editStatusVeiculo');
    Route::post('delete/veiculos', 'App\Http\Controllers\VeiculoController@deleteVeiculos');
    Route::get('get/motoristas', 'App\Http\Controllers\VeiculoController@getMotoristas');
    Route::get('get/veiculos/disponiveis', 'App\Http\Controllers\VeiculoController@getVeiculosDisponiveis');

    Route::post('insert/usuario', 'App\Http\Controllers\UsuarioController@insertUsuario');
    Route::get('usuarios', 'App\Http\Controllers\UsuarioController@getUsuarios');
    Route::get('get/usur', 'App\Http\Controllers\UsuarioController@getUsur');
    Route::post('edit/usuario', 'App\Http\Controllers\UsuarioController@editUsuarios');
    Route::post('delete/usuario', 'App\Http\Controllers\UsuarioController@deleteUsuarios');

    Route::post('insert/rotas', 'App\Http\Controllers\RotasController@insertRotas');
    Route::post('update/rotas', 'App\Http\Controllers\RotasController@updateRotas');
    Route::post('update/obs/rota', 'App\Http\Controllers\RotasController@updateObsRotas');
    Route::get('busca/cep/{cep}', 'App\Http\Controllers\RotasController@buscarEndereco');
    Route::get('get/rotas', 'App\Http\Controllers\RotasController@getRotas');
    Route::get('get/obs/rotas', 'App\Http\Controllers\RotasController@getObsRotas');
    Route::get('get/status/rotas', 'App\Http\Controllers\RotasController@getStatusRotas');
    Route::post('edit/status/rota', 'App\Http\Controllers\RotasController@editStatusRota');
    Route::post('rota/linkMotorista', 'App\Http\Controllers\RotasController@linkMotorista');
    Route::post('rota/hodometro/insert', 'App\Http\Controllers\RotasController@insertHodometro');
    Route::post('rota/insertPartida', 'App\Http\Controllers\RotasController@insertPartida');
    Route::post('rota/getRotaMobile', 'App\Http\Controllers\RotasController@getRotasMobile');
    Route::post('directions', 'App\Http\Controllers\RotasController@getDirections');
    Route::post('rota/horaPartida', 'App\Http\Controllers\RotasController@insertHoraPartida');
    Route::post('rota/horaChegada', 'App\Http\Controllers\RotasController@insertHoraChegada');
    Route::get('rota/duracao', 'App\Http\Controllers\RotasController@getDuracaoRota');
    Route::post('rota/insertRouteInfo', 'App\Http\Controllers\RotasController@insertRouteInfo');
    Route::post('rota/insertRouteSteps', 'App\Http\Controllers\RotasController@insertRouteSteps');
    Route::get('fetch/viagens', 'App\Http\Controllers\RotasController@fetchViagens');
    Route::get('fetch/maisInfo/viagens', 'App\Http\Controllers\RotasController@fetchMaisInfoViagens');
    Route::post('rota/atualizarStatus', 'App\Http\Controllers\RotasController@editStatusRotaMobile');
    Route::post('rota/getStepsRota', 'App\Http\Controllers\RotasController@getStepsRota');
    
    Route::get('get/relatorio/rotas', 'App\Http\Controllers\RelatoriosController@getRelatorioRotas');
    
// });