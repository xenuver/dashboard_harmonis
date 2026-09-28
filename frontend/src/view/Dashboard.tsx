import { useState } from "react";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';
import type { DashboardKpiView } from "../../types/DasboardKpiView";

// import session from "../services/SessionManager";

import KpiCard from "../components/dashboard/KpiCard";

export function Dashboard() {
  const [showpreviousResult, setShowPreviousResult] = useState(true);
  const [graphLegendInternal, setGraphLegendInternal] = useState("harian");
  const [sortLaris, setSortLaris] = useState("terlaris");

  let requestLoading = true;

  let kpiView: DashboardKpiView = {
    total_sales: {
      value: null,
    },
    total_growth: {
      value: null,
      direction: "neutral"
    },
    jumlah_transaksi: {
      value: null,
    },
    growth_member: {
      value: null,
      direction: "neutral"
    }
  }

  // async function loadDashboard() {
  //   const backendProtocol = location.protocol;
  //   const backendHostname = location.hostname;
  //   const backendPort = 8000;

  //   const data = await fetch(`${backendProtocol}//${backendHostname}:${backendPort}/api/dashboard`, {
  //     method: "GET",
  //     headers: {
  //       "Authrorization": `Bearer ${session.getToken}`
  //     },
  //   });
  // }

  const aResponse = {
    kpi: {
      total_sales: 5000,
      total_growth: -5.1,
      jumlah_transaksi: 252,
      growth_member: 4
    },

    trend: {
      "2026-09-01": [
        {
          "cabang": "Cabang Ampera",
          "total": 15000000
        },
        {
          "cabang": "Cabang Pal",
          "total": 8500000
        }
      ],
      "2026-09-02": [
        {
          "cabang": "Cabang Ampera",
          "total": 12000000
        },
        {
          "cabang": "Cabang Pal",
          "total": 12000000
        }
      ],
      "2026-09-03": [
        {
          "cabang": "Cabang Ampera",
          "total": 12000000
        },
        {
          "cabang": "Cabang Pal",
          "total": 12000000
        }
      ],
      "2026-09-04": [
        {
          "cabang": "Cabang Ampera",
          "total": 12000000
        },
        {
          "cabang": "Cabang Pal",
          "total": 12000000
        }
      ],
      "2026-09-05": [
        {
          "cabang": "Cabang Pal",
          "total": 12000000
        }
      ],
      "2026-09-07": [
        {
          "cabang": "Cabang Ampera",
          "total": 12000000
        },
        {
          "cabang": "Cabang Pal",
          "total": 12000000
        }
      ]
    },

    top_produk_terlaris: [
      {
        'upload_id': 32489273943,
        'kategori': "tertinggi",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "tertinggi",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "tertinggi",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "tertinggi",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "tertinggi",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
    ],

    top_produk_terendah: [
      {
        'upload_id': 32489273943,
        'kategori': "terendah",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "terendah",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "terendah",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "terendah",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kategori': "terendah",
        'barcode': 239462378435,
        'kode_brg': "D4309PT5UJ4ORT",
        'nama_brg': "penangkas nyamuk",
        'satuan': "pcs",
        'harga_jual': 40000,
        'jumlah': 24325424,
        'qty': 345234,
        'stok': 346534,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
    ],

    top_supplier: [
      {
        'upload_id': 32489273943,
        'kode_supp': 'GEROT08YO8FR',
        'nama_supp': "PT Laboratorium Nuklir Tbk.",
        'gross_total': 50000000,
        'net_sales_total': 20000000,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kode_supp': 'GEROT08YO8FR',
        'nama_supp': "PT Laboratorium Nuklir Tbk.",
        'gross_total': 50000000,
        'net_sales_total': 20000000,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kode_supp': 'GEROT08YO8FR',
        'nama_supp': "PT Laboratorium Nuklir Tbk.",
        'gross_total': 50000000,
        'net_sales_total': 20000000,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kode_supp': 'GEROT08YO8FR',
        'nama_supp': "PT Laboratorium Nuklir Tbk.",
        'gross_total': 50000000,
        'net_sales_total': 20000000,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
      {
        'upload_id': 32489273943,
        'kode_supp': 'GEROT08YO8FR',
        'nama_supp': "PT Laboratorium Nuklir Tbk.",
        'gross_total': 50000000,
        'net_sales_total': 20000000,
        "created_at": "2026-09-28T08:00:00.000000Z",
        "updated_at": "2026-09-28T08:00:00.000000Z"
      },
    ]
  }

  function parseKpi() {
    if(typeof aResponse.kpi.total_sales === "number") {
      kpiView.total_sales.value  = `Rp ${aResponse.kpi.total_sales}`;
    } else {
      console.warn(`respon total_sales dari backend tidak terduga: ${aResponse.kpi.total_sales}`);
      kpiView.total_sales.value = "-";
    }

    if(typeof aResponse.kpi.total_growth === "number") {
      if(aResponse.kpi.total_growth > 0) {
        kpiView.total_growth.value = `+${aResponse.kpi.total_growth}%`;
        kpiView.total_growth.direction = "up";
      } else if (aResponse.kpi.total_growth === 0) {
        kpiView.total_growth.value = `${aResponse.kpi.total_growth}%`;
        kpiView.total_growth.direction = "neutral";
      } else {
        kpiView.total_growth.value = `-${aResponse.kpi.total_growth}%`;
        kpiView.total_growth.direction = "down";
      }
    } else {
      console.warn(`respon total_growth dari backend tidak terduga: ${aResponse.kpi.total_sales}`);
      kpiView.total_growth.value = "-";
      kpiView.total_growth.direction = "neutral";
    }

    if(typeof aResponse.kpi.jumlah_transaksi === "number") {
      kpiView.jumlah_transaksi.value  = `Rp ${aResponse.kpi.jumlah_transaksi}`;
    } else {
      console.warn(`respon jumlah_transaksi dari backend tidak terduga: ${aResponse.kpi.total_sales}`);
      kpiView.jumlah_transaksi.value = "-";
    }

    if(typeof aResponse.kpi.growth_member === "number") {
      if(aResponse.kpi.growth_member > 0) {
        kpiView.growth_member.value = `+${aResponse.kpi.growth_member}`;
        kpiView.growth_member.direction = "up";
      } else if (aResponse.kpi.growth_member === 0) {
        kpiView.growth_member.value = `${aResponse.kpi.growth_member}`;
        kpiView.growth_member.direction = "neutral";
      } else {
        kpiView.growth_member.value = `-${aResponse.kpi.growth_member}`;
        kpiView.growth_member.direction = "down";
      }
    } else {
      console.warn(`respon growth_member dari backend tidak terduga: ${aResponse.kpi.growth_member}`);
      kpiView.growth_member.value  = "-";
      kpiView.growth_member.direction = "neutral";
    }
  }

  let data = [
    {name: '1', Penjualan: 400},
    {name: '2', Penjualan: 300},
    {name: '3', Penjualan: 320},
    {name: '4', Penjualan: 200},
    {name: '5', Penjualan: 278},
    {name: '6', Penjualan: 189},
  ];

  requestLoading = false;
  parseKpi();

  return (<>
  <input type="checkbox" onChange={(e) => setShowPreviousResult(e.target.checked)} checked={showpreviousResult}></input> <div>bandingkan periode sebelumnya</div>

  <KpiCard title="Total Penjualan" value={kpiView.total_sales.value} showComparison={showpreviousResult} comparison={{
    value: "+5%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} isLoading={requestLoading} />
  <KpiCard title="Total Growth" value={kpiView.total_sales.value} showComparison={showpreviousResult} comparison={{
    value: "-1%",
    label: " dari periode sebelumnya",
    trend: "down"
  }} isLoading={requestLoading} />
  <KpiCard title="Jumlah Transaksi" value={kpiView.jumlah_transaksi.value} showComparison={showpreviousResult} comparison={{
    value: "+9%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} isLoading={requestLoading} />
  <KpiCard title="Growth Member" value={kpiView.growth_member.value} showComparison={showpreviousResult} comparison={{
    value: "+7%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} isLoading={requestLoading}/>

  <LineChart style={{ width: '100%', aspectRatio: 1.618, maxWidth: 600 }} responsive data={data}>
    <CartesianGrid />
    <Line dataKey="Penjualan" isAnimationActive={false} />
    <XAxis dataKey="name" />
    <YAxis />
    <Tooltip isAnimationActive={false} />
    <Legend />
  </LineChart>

  <table border={1}>
    <tr>
      <td colSpan={2}>Peringkat Produk</td>
      <td colSpan={2}>Produk paling laris/Produk kurang laris</td>
    </tr>
    <tr>
      <td colSpan={2}>Nama Barang</td>
      <td>Qty Terjual</td>
      <td>Jumlah (Rp)</td>
    </tr>
    <tr>
      <td>1</td>
      <td>cerek</td>
      <td>998</td>
      <td>25.000.000</td>
    </tr>
    <tr>
      <td>2</td>
      <td>Kue</td>
      <td>877</td>
      <td>20.000.000</td>
    </tr>
    <tr>
      <td>3</td>
      <td>Shampo</td>
      <td>665</td>
      <td>15.000.000</td>
    </tr>
    <tr>
      <td>4</td>
      <td>sabun</td>
      <td>544</td>
      <td>10.000.000</td>
    </tr>
    <tr>
      <td>5</td>
      <td>Makanan</td>
      <td>332</td>
      <td>5.000.000</td>
    </tr>
    <tr>
      <td colSpan={4}>Lihat Peringkat Produk</td>
    </tr>
  </table>

  </>);
}

export default Dashboard
