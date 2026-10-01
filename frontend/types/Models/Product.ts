export interface Product {
      id: number,
      upload_id: number,
      barcode: string,
      kode_brg: string,
      nama_brg: string,
      satuan: string,
      harga_jual: number,
      jumlah: number,
      qty: number,
      stok: number,
      created_at?: string,// Date
      updated_at?: string // Date
}