export function UploadData() {
    const yearOptions: number[] = [];

    let yearNow = new Date().getFullYear();

    for (let i = 0; i < 100; i++) {
        yearOptions.push(yearNow - i);
    }

    return (<>
    <h1>Upload Data</h1>
    <div>
        <form>
            <h2>Informasi Laporan</h2>
            <select required={true} name="periode_bulan" defaultValue="Agustus">
                {
                    ["Januari","Februari","Maret","April","Mei","Juni","Juli",
                    "Agustus","September","Oktober","November","Desember"].map((value, index) => (
                        <option tabIndex={index + 1} value={value} key={index} defaultValue={0}></option>
                    ))
                }
            </select>
            <select required={true} name="periode_tahun" defaultValue={yearOptions[0]}>
                {
                    yearOptions.map((value, index) => (
                        <option tabIndex={index + 1} value={value} key={index}></option>
                    ))
                }
            </select>
            <h2>Unggah File</h2>
            <p>Tekan tombol di bawah untuk mulai memilih file</p>
            <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required={true} />
            <button type="submit">Unggah File</button>
        </form>
    </div>
    </>);
}

export default UploadData;