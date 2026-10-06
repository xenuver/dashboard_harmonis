import { useState as reactUseState, useEffect, useRef } from "react";
import { isAxiosError, isCancel as axiosIsCancel } from "axios";
import { api } from "../services/api";
import { useNavigate } from "react-router-dom";
import { formatAngka, formatRupiah } from "../services/formatters";

import type { ProductSearchResponse } from "../../types/ProductSearchResponse";
import type { Product } from "../../types/Models/Product";

const ERROR_CODE_SEARCH_NO_ERROR = 0;
const ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE = 1; // request valid, tapi laporan belum ada
const ERROR_CODE_SEARCH_NO_DATA = 2;
const ERROR_CODE_SEARCH_CONNECTION_ERROR = 3;
const ERROR_CODE_SEARCH_UNKNOWN_ERROR = 4;

export function ProdukPenjualan() {
  const navigate = useNavigate();
  const searchHasMounted = useRef(false);

  const [jenisLaporanSaatIni, setJenisLaporanSaatIni] = reactUseState("ampera");
  const [kataKunciPencarian, setKataKunciPencarian] = reactUseState < string | undefined > (undefined);
  const [sortirTingkatProduk, setSortirTingkatProduk] = reactUseState("tertinggi");
  const [productPerPage, setProductPerPage] = reactUseState(10);
  const [daftarProduk, setDaftarProduk] = reactUseState < Product[] > ([]);

  const [currentPage, setCurrentPage] = reactUseState < number > (1);
  const [totalProducts, setTotalProducts] = reactUseState < number > (0);
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
      setDaftarProduk([]);
      setTotalProducts(0);
      setIsLoaded(false);

      // construct query params
      const body = {
        page: currentPage,
        jenis_laporan: jenisLaporanSaatIni,
        periode_tahun: yearNow,
        periode_bulan: monthNow,
        per_page: productPerPage,
        kategori: sortirTingkatProduk
      }

      if(kataKunciPencarian) {
        Object.defineProperty(body, "search", {value: kataKunciPencarian, enumerable: true});
      }

      try {
        const response = await api.get('/api/produk-penjualan', {
          responseType: "json",
          signal: abortController.signal,
          params: body
        });

        const responseData = response.data as ProductSearchResponse;

        setDaftarProduk(responseData.data);
        setTotalProducts(responseData.total);
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
  }, [jenisLaporanSaatIni, productPerPage, sortirTingkatProduk, resetSearchTrigger]);

    return (<>
      <h1>Peringkat Produk</h1>
      <div>Cabang saat ini:</div>
      <select required={true} onChange={(e) => setJenisLaporanSaatIni(e.target.value)}>
        {
          ["ampera", "pal", "gabungan"].map((value, index) => (
            <option tabIndex={index + 1} value={value} key={index} defaultValue={0}>{value}</option>
          ))
        }
      </select>
      <div>
        <div><input type="radio" name="sortir_tingkat" value="tertinggi" onChange={(e) => setSortirTingkatProduk(e.target.value)} checked={ sortirTingkatProduk === "tertinggi" } />Tertinggi</div>
      </div>
      <div>
        <div><input type="radio" name="sortir_tingkat" value="terendah" onChange={(e) => setSortirTingkatProduk(e.target.value) } checked={ sortirTingkatProduk === "terendah" } />Terendah</div>
      </div>
      <input type="text" onChange={(e) => setKataKunciPencarian(e.target.value)} placeholder="Masukkan kata kunci di sini" onKeyUp={(e) => e.keyCode === 13 && resetSearchNow()} />
      <button onClick={resetSearchNow}>Cari</button>

      { errorCode === ERROR_CODE_SEARCH_NO_DATA && 
        <div>
          <div>Produk tidak ditemukan. Pastikan kode atau kata kunci produk dimasukkan dengan benar</div>
        </div>
      }

      { errorCode === ERROR_CODE_SEARCH_REPORT_NOT_AVAILABLE && 
        <div>
          <div>Laporan produk untuk jenis dan masa yang dimasukkan masih belum tersedia. Silahkan unggah laporan penjualan untuk jenis dan masa tersebut terlebih dahulu.</div>
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
            <td>barcode</td>
            <td>kode barang</td>
            <td>nama barang</td>
            <td>satuan</td>
            <td>harga jual</td>
            <td>jumlah (omzet)</td>
            <td>qty (terjual)</td>
            <td>sisa stok</td>
          </tr>
          { isLoaded === true ?
            daftarProduk.map((produk, index) => (
              <tr key={index}>
                <td>{index + 1}</td>
                <td>{produk.barcode}</td>
                <td>{produk.kode_brg}</td>
                <td>{produk.nama_brg}</td>
                <td>{produk.satuan}</td>
                <td>{formatRupiah(produk.harga_jual)}</td>
                <td>{formatRupiah(produk.jumlah)}</td>
                <td className={produk.qty < 0 ? "font-semibold text-purple-600" : ""}>{formatAngka(produk.qty)}</td>
                <td>{formatAngka(produk.stok)}</td>
              </tr>
            ))
          :
            Array.from({ length: productPerPage }).map((_, index) => (
              <tr key={index}>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
                <td className="bg-gray-200 animate-pulse loading-skeleton">&nbsp;</td>
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
          <div>{`Menampilkan produk ${productPerPage * (currentPage - 1)}-${(productPerPage * (currentPage - 1)) + daftarProduk.length} dari ${totalProducts}`}</div>
          <div>
            <div>Produk per halaman</div>
            <select onChange={(e) => setProductPerPage(parseInt(e.target.value))} defaultValue={10}>
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

export default ProdukPenjualan;
