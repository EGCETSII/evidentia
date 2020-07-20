@extends('layouts.app')

@section('title', 'Gestionar eventos')

@section('title-icon', 'fas fa-calendar-alt')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="/{{$instance}}">Home</a></li>
    <li class="breadcrumb-item active">@yield('title')</li>
@endsection

@section('info')
    <x-slimreminder :datetime="\Config::attendee_timestamp()"/>
@endsection

@section('content')

    <div class="row">
        <div class="col-lg-12">

            <x-status/>

            <div class="row mb-3">
                <div class="col-lg-3 mt-1">
                    <a href="{{route('registercoordinator.event.load',['instance' => $instance])}}" class="btn btn-primary btn-block" role="button"><i class="fas fa-cloud-download-alt"></i> &nbsp;Cargar eventos desde Eventbrite</a>
                </div>
            </div>

            <div class="card">


                <div class="card-body">
                    <table id="dataset" class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Descripción</th>
                            <th scope="col">Fecha de inicio</th>
                            <th scope="col">Fecha de fin</th>
                            <th scope="col">Capacidad</th>
                            <th scope="col">Horas</th>
                            <th scope="col">Estado</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($events as $event)
                            <tr scope="row">
                                <td><a href="{{$event->url}}" target="_blank">{!! $event->name !!}</a></td>
                                <td>{!! $event->description !!}</td>
                                <td>{{ \Carbon\Carbon::parse($event->start_datetime) }}</td>
                                <td>{{ \Carbon\Carbon::parse($event->end_datetime) }}</td>
                                <td>{{ $event->capacity }}</td>
                                <td>{{ $event->hours }}</td>
                                <td><x-eventstatus :event="$event"/></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                </div>

            </div>


        </div>
    </div>

@endsection
