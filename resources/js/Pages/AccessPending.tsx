import Layout from '../components/Layout';
import { Empty } from '../components/ui';

export default function AccessPending() {
    return (
        <Layout title="Data Masyarakat" description="Akun Anda telah terhubung.">
            <Empty>
                <strong>Hak akses belum ditetapkan.</strong>
                <p>
                    Ketua perlu menentukan izin akun sesuai matriks organisasi sebelum data
                    ditampilkan.
                </p>
            </Empty>
        </Layout>
    );
}
