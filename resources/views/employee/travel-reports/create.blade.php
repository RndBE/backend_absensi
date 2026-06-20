@extends('employee.layouts.app')
@section('title', 'Buat LHP')

@section('content')
@include('employee.travel-reports.partials.form', [
    'title' => 'Buat LHP',
    'subtitle' => 'Buat laporan hasil perjalanan seperti di aplikasi mobile.',
    'action' => route('employee.travel-reports.store'),
    'method' => 'POST',
    'report' => null,
])
@endsection
