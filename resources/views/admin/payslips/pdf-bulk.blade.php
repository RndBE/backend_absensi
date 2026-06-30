{{-- Gabungan semua payslip dalam satu PDF. $payslips = array of [detail, company, logoBase64, bpjsData, loanSummary, hideBenefits] --}}
@include('admin.payslips._document-head')
<body>
@foreach($payslips as $i => $p)
    @if($i > 0)
        <div style="page-break-before: always;"></div>
    @endif
    @include('admin.payslips._content', $p)
@endforeach
</body>
</html>
