import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { useNavigate } from "react-router-dom";
import { formatRupiah } from "../services/formatters";

import type { SupplierSearchResponse } from "../../types/SupplierSearchResponse";
import type { Supplier } from "../../types/Models/Supplier";

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function SupplierPenjualan() {
  const navigate = useNavigate();
  const searchHasMounted = useRef(false);

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("ampera");
  const [kataKunciPencarian, setKataKunciPencarian] = reactUseState < string | undefined > (undefined);
  const [supplierPerPage, setSupplierPerPage] = reactUseState(10);
  const [daftarSupplier, setDaftarSupplier] = reactUseState < Supplier[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalSuppliers, setTotalSuppliers] = reactUseState < number > (0);
  const [maxPage, setMaxPage] = reactUseState < number > (1);

  const [isLoaded, setIsLoaded] = reactUseState < boolean > (false);
  const [errorCode, setErrorCode] = reactUseState < number > (ERROR_CODE_SEARCH_NO_ERROR);
  const [retryNowTrigger, shouldRetryNow] = reactUseState < boolean > (false);
  const [resetSearchTrigger, shouldResetSearchNow] = reactUseState < boolean > (false);

  const yearNow = new Date().getFullYear();
  const monthNow = new Date().getMonth() + 1;

  function retrySearchNow() {
    shouldRetryNow((prev) => !prev);
  }

  function resetSearchNow() {
    shouldResetSearchNow((prev) => !prev);
  }

  useEffect(() => {
    const abortController = new AbortController();

    async function fetchData() {
      setErrorCode(ERROR_CODE_SEARCH_NO_ERROR);
      setDaftarSupplier([]);
      setTotalSuppliers(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        jenis_laporan: jenisLaporanSaatIni,
        periode_tahun: yearNow,
        periode_bulan: monthNow,
        per_page: supplierPerPage,
      }

      if(kataKunciPencarian) {
        Object.defineProperty(body, "search", {value: kataKunciPencarian, enumerable: true});
      }

      try {
        const response = await api.get('/api/supplier-penjualan', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as SupplierSearchResponse;

        setDaftarSupplier(responseData.data);
        setTotalSuppliers(responseData.total);
        setMaxPage(responseData.last_page);

        if (responseData.data.length === 0) {
          setErrorCode(ERROR_CODE_SEARCH_NO_DATA);
          return;
        }
        setIsLoaded(true);
      } catch (err) {
        if (axiosIsCancel(err)) {
          return;
        }

        if (isAxiosError(err)) {
          if (err.status === 404) {
            setErrorCode(ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE);
            return;
          }
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_CONNECTION_ERROR);
        } else {
          console.error(err);
          setErrorCode(ERROR_CODE_SEARCH_UNKNOWN_ERROR);
        }
      }
    }
    fetchData();

    return () => {
      abortController.abort();
    };
  }, [retryNowTrigger, currentPage]);

  useEffect(function() {
    // pastikan kode tidak dijalankan pada tahap mounting
    if(searchHasMounted.current) {
      setIsLoaded(false);
      setCurrentPage(1);
      retrySearchNow();
    } else {
      searchHasMounted.current = true;
    }
  }, [jenisLaporanSaatIni, supplierPerPage, resetSearchTrigger]);

    return (<>
      <h1>Peringkat Supplier</h1>
      <div>Cabang saat ini:</div>
      <select required={true} onChange={(e) => setJenisLaporanSaatIni(e.target.value)}>
        {
          ["ampera", "pal", "gabungan"].map((value, index) => (
            <option tabIndex={index + 1} value={value} key={index} defaultValue={0}>{value}</option>
          ))
        }
      </select>
      <input type="text" onChange={(e) => setKataKunciPencarian(e.target.value)} placeholder="Masukkan kata kunci di sini" onKeyUp={(e) => e.keyCode === 13 && resetSearchNow()} />
      <button onClick={resetSearchNow}>Cari</button>

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <div>
          <div>Supplier tidak ditemukan. Pastikan kode atau kata kunci supplier dimasukkan dengan benar</div>
        </div>
      }

      { errorCode === ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE && 
        <div>
          <div>Laporan supplier untuk jenis dan masa yang dimasukkan masih belum tersedia. Silahkan unggah laporan penjualan untuk jenis dan masa tersebut terlebih dahulu.</div>
          <button onClick={()=> navigate("/upload") }>Menuju halaman unggah data</button>
        </div>
      }

      { errorCode === ERROR_CODE_SEARCH_UNKNOWN_ERROR &&
        <>
        <div>Terjadi kesalahan yang tidak diketahui. Mohon coba lagi setelah beberapa saat.</div>
        <button onClick={retrySearchNow}>Coba Lagi</button>
        </>
      }

      { errorCode === ERROR_CODE_SEARCH_NO_ERROR && 
        <table border={1}>
          <tr>
            <td>no</td>
            <td>kode supplier</td>
            <td>nama supplier</td>
            <td>gross total</td>
            <td>net sales total</td>
          </tr>
          { isLoaded === true ?
            daftarSupplier.map((supplier, index) => (
              <tr key={index}>
                <td>{index + 1}</td>
                <td>{supplier.kode_supp}</td>
                <td>{supplier.nama_supp}</td>
                <td>{formatRupiah(supplier.gross_total)}</td>
                <td>{formatRupiah(supplier.net_sales_total)}</td>
              </tr>
            ))
          :
            Array.from({ length: supplierPerPage }).map((_, index) => (
              <tr key={index}>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
              </tr>
            ))
          }
        </table>
      }
      { (errorCode === ERROR_CODE_SEARCH_NO_ERROR || errorCode === ERROR_CODE_SEARCH_NO_DATA || errorCode === ERROR_CODE_SEARCH_CONNECTION_ERROR) &&
        <div>
          <div>
            <div>Halaman...</div>
            { currentPage - 2 >= 1 && <div onClick={() => setCurrentPage(1)}>{1}</div> }
            {currentPage - 1 > 0 && <div onClick={() => setCurrentPage(currentPage - 1)}>{currentPage - 1}</div> }
            <div>{currentPage}</div>
            {currentPage + 1 <= maxPage && <div onClick={() => setCurrentPage(currentPage + 1)}>{currentPage + 1}</div>}
            { currentPage + 2 <= maxPage && <div onClick={() => setCurrentPage(maxPage)}>{maxPage}</div> }
          </div>
          <div>{`Menampilkan supplier ${supplierPerPage * (currentPage - 1)}-${(supplierPerPage * (currentPage - 1)) + daftarSupplier.length} dari ${totalSuppliers}`}</div>
          <div>
            <div>Supplier per halaman</div>
            <select onChange={(e) => setSupplierPerPage(parseInt(e.target.value))} defaultValue={10}>
            {
              [10,20,50].map((value, index) => (
                <option tabIndex={index + 1} value={value} key={index}>{value}</option>
              ))
            }
            </select>
          </div>
        </div>
      }
    </>);
}

export default SupplierPenjualan;
