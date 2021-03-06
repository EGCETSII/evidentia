@extends('layouts.app')

@section('title', 'Restablecer contraseña')

@section('title-icon', 'fas fa-unlock-alt')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/">Home</a></li>
    <li class="breadcrumb-item"><a href="/{{\Instantiation::instance()}}">Acceso del alumno</a></li>
    <li class="breadcrumb-item active">@yield('title')</li>
@endsection

@section('content')

    <div class="row">
        <div class="col-lg-6 col-sm-12">

            <x-status/>

            <div class="card">

                <div class="card-header">
                    <h3 class="card-title">
                        <span class="badge badge-secondary">{{\Instantiation::instance_entity()->name}}</span>
                    </h3>

                </div>

                <div class="card-body">
                    <form action="{{route('password.reset_p',$instance)}}" method="post">
                        @csrf

                        <x-input col="12" attr="email" type="text" label="Email" type="email" description="Introduce tu email corporativo (incluyendo @alum.us.es)"/>

                        <div class="form-group col-sm-12 col-lg-6">
                            <button type="submit" class="btn btn-primary btn-block">Mándame link de restablecimiento</button>

                        </div>

                    </form>
                </div>

            </div>

        </div>

        <div class="col-lg-6 col-sm-12">

            <div class="callout callout-info">
                <x-evidentiadescription/>
            </div>



        </div>
    </div>

@endsection
