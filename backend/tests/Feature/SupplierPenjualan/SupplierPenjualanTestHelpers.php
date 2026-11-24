<?php

namespace Tests\Feature\SupplierPenjualan;

trait SupplierPenjualanTestHelpers
{
    private const FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE = [
        'current_page',
        'data' => [
            '*' => [
                'id',
                'upload_id',
                'kode_supp',
                'nama_supp',
                'gross_total',
                'net_sales_total',
            ],
        ],
        'first_page_url',
        'from',
        'last_page',
        'last_page_url',
        'links',
        'next_page_url',
        'path',
        'per_page',
        'prev_page_url',
        'to',
        'total',
    ];

    private function supplierUrl(array $params = []): string
    {
        $base = '/api/supplier-penjualan';

        return empty($params) ? $base : $base.'?'.http_build_query($params);
    }
}
