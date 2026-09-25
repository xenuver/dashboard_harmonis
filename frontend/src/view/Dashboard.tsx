import { useState } from "react";
import { CartesianGrid, Legend, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';

// import session from "../services/SessionManager";

import KpiCard from "../components/dashboard/KpiCard";

export function Dashboard() {
  const [showpreviousResult, setShowPreviousResult] = useState(true);
  const [graphLegendInternal, setGraphLegendInternal] = useState("harian");
  const [sortLaris, setSortLaris] = useState("terlaris");


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

  let data = [
    {name: '1', Penjualan: 400},
    {name: '2', Penjualan: 300},
    {name: '3', Penjualan: 320},
    {name: '4', Penjualan: 200},
    {name: '5', Penjualan: 278},
    {name: '6', Penjualan: 189},
  ];

  return (<>
  <input type="checkbox" onChange={(e) => setShowPreviousResult(e.target.checked)} checked={showpreviousResult}></input> <div>bandingkan periode sebelumnya</div>

  <KpiCard title="Total Penjualan" value="Rp 50.000" showComparison={showpreviousResult} comparison={{
    value: "+5%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} />
  <KpiCard title="Total Growth" value="+3%" showComparison={showpreviousResult} comparison={{
    value: "-1%",
    label: " dari periode sebelumnya",
    trend: "down"
  }} />
  <KpiCard title="Jumlah Transaksi" value="23.000" showComparison={showpreviousResult} comparison={{
    value: "+9%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} />
  <KpiCard title="Growth Member" value="+4" showComparison={showpreviousResult} comparison={{
    value: "+7%",
    label: " dari periode sebelumnya",
    trend: "up"
  }} />

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
