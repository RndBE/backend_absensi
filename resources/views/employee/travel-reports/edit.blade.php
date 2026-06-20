@extends('employee.layouts.app')
@section('title', 'Edit LHP')

@section('content')
@include('employee.travel-reports.partials.form', [
    'title' => 'Edit LHP',
    'subtitle' => 'Perbarui LHP sebelum proses approval selesai.',
    'action' => route('employee.travel-reports.update', $report->id),
    'method' => 'PUT',
    'report' => $report,
])
@endsection
