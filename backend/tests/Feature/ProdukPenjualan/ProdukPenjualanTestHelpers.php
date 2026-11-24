<?php

namespace Tests\Feature\ProdukPenjualan;

trait ProdukPenjualanTestHelpers
{
    private const FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE = [
        'current_page',
        'data' => [
            '*' => [
                'id',
                'upload_id',
                'kategori',
                'barcode',
                'kode_brg',
                'nama_brg',
                'satuan',
                'harga_jual',
                'jumlah',
                'qty',
                'stok',
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

    private function produkUrl(array $params = []): string
    {
        $base = '/api/produk-penjualan';

        return empty($params) ? $base : $base.'?'.http_build_query($params);
    }
}
