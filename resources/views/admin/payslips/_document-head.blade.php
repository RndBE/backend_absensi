<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Payslip</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9.5px;
        color: #1a1a1a;
        background: #fff;
        padding: 28px 30px;
    }

    .w100 { width: 100%; }
    .bold { font-weight: bold; }
    .right { text-align: right; }
    .muted { color: #6b7280; }
    .red { color: #dc2626; font-weight: bold; font-size: 9px; letter-spacing: 0.5px; }

    .company-name { font-size: 16px; font-weight: bold; color: #111; }
    .company-addr { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .doc-title { font-size: 15px; font-weight: bold; letter-spacing: 3px; color: #111; }

    .divider-top { border-top: 2px solid #111; padding-top: 10px; margin-bottom: 12px; }

    .info-tbl td { padding: 2px 0; font-size: 9.5px; vertical-align: top; }
    .info-key { color: #6b7280; width: 130px; }
    .info-sep { width: 10px; color: #6b7280; }
    .info-val { font-weight: 600; }
    .info-gap { width: 30px; }

    .main-tbl { border-collapse: collapse; margin-top: 14px; }
    .main-tbl th {
        background: #f3f4f6;
        padding: 7px 10px;
        text-align: left;
        font-size: 9.5px;
        font-weight: bold;
        border: 1px solid #d1d5db;
    }
    .main-tbl td {
        padding: 4.5px 10px;
        font-size: 9.5px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: top;
    }
    .main-tbl td.divider { border-left: 1px solid #d1d5db; }
    .main-tbl .num { text-align: right; white-space: nowrap; }
    .loan-detail { margin-top: 2px; font-size: 8px; line-height: 1.25; color: #6b7280; }
    .main-tbl .total-row td {
        border-top: 1.5px solid #d1d5db;
        border-bottom: none;
        background: #f9fafb;
        font-weight: bold;
        font-size: 9.5px;
    }
    .split-panels {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 14px;
    }
    .split-panels td {
        vertical-align: top;
    }
    .split-panels .panel-left,
    .split-panels .panel-right {
        width: 49%;
    }
    .split-panels .panel-gap {
        width: 12px;
    }
    .pay-panel {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #d1d5db;
    }
    .pay-panel th {
        padding: 7px 9px;
        background: #374151;
        color: #fff;
        border-bottom: 1px solid #d1d5db;
        font-size: 9px;
        font-weight: bold;
        text-align: left;
    }
    .pay-panel td {
        padding: 5px 10px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 9.5px;
        vertical-align: top;
    }
    .pay-panel .num { text-align: right; white-space: nowrap; width: 92px; }
    .pay-panel .blank-row td {
        color: transparent;
    }
    .pay-panel .panel-total td {
        border-top: 1.5px solid #d1d5db;
        border-bottom: none;
        background: #f9fafb;
        font-weight: bold;
    }

    .thp-tbl { border-collapse: collapse; margin-top: 0; }
    .thp-tbl td {
        padding: 9px 10px;
        border: 1px solid #d1d5db;
        border-top: none;
        font-size: 13px;
        font-weight: bold;
    }
    .thp-tbl td.thp-right { text-align: right; }

    .benefits-section { margin-top: 18px; }
    .benefits-title { font-size: 10px; font-weight: bold; margin-bottom: 7px; }
    .ben-tbl { border-collapse: collapse; }
    .ben-tbl td { padding: 2.5px 0; font-size: 9.5px; }
    .ben-tbl .ben-lbl { width: 220px; }
    .ben-tbl .ben-amt { text-align: right; width: 90px; white-space: nowrap; }
    .ben-tbl .ben-muted { color: #6b7280; }
    .ben-total td {
        border-top: 1px solid #111;
        padding-top: 5px;
        font-weight: bold;
    }
    .benefit-notes {
        margin-top: 18px;
        color: #9ca3af;
        font-size: 8.5px;
        line-height: 1.55;
    }

    .footer { margin-top: 24px; text-align: center; font-size: 8px; color: #9ca3af; }
</style>
</head>
