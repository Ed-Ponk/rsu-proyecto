@extends('adminlte::page')

@section('css')
    @parent
    @vite(['resources/css/admin/menu.css', 'resources/css/admin/panel.css'])
@stop

@section('js')
    @parent
    @vite('resources/js/admin/panel.js')
@stop
