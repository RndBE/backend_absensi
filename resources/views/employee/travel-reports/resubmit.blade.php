@extends('employee.layouts.app')
@section('title', 'Ajukan Ulang LHP')

@section('content')
@include('employee.travel-reports.partials.form', [
    'title' => 'Ajukan Ulang LHP',
    'subtitle' => 'Perbaiki LHP yang ditolak lalu kirim lagi. Approval dimulai lagi dari step 1.',
    'action' => route('employee.travel-reports.store'),
    'method' => 'POST',
    'report' => $report,
    'resubmissionOf' => $report,
])
@endsection
