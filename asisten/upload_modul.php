<?php
session_start(); // Harus ada di awal
require_once __DIR__ . '/../config.php';

// Ambil mk_id dari URL
$mk_id = isset($_GET['mk_id']) ? intval($_GET['mk_id']) : 0;

// Validasi apakah mk_id valid
if ($mk_id <= 0) {
    $_SESSION['error'] = "ID Mata Kuliah tidak valid.";
    header("Location: mataKuliah.php");
    exit();
}

// Query untuk ambil nama mata kuliah (untuk ditampilkan di halaman)
$query_mk = "SELECT nama_mata_kuliah FROM mata_kuliah WHERE id = ?";
$stmt = $conn->prepare($query_mk);
$stmt->bind_param("i", $mk_id);
$stmt->execute();
$result_mk = $stmt->get_result();

if ($result_mk->num_rows === 0) {
    $_SESSION['error'] = "Mata kuliah tidak ditemukan.";
    header("Location: mataKuliah.php");
    exit();
}

$row_mk = $result_mk->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_modul = htmlspecialchars(trim($_POST['nama_modul']));
    $pertemuan = intval($_POST['pertemuan']);
    $deskripsi = htmlspecialchars(trim($_POST['deskripsi']));

    // Upload file
    if (isset($_FILES['materi_file']) && $_FILES['materi_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file_name = $_FILES['materi_file']['name'];
        $file_tmp = $_FILES['materi_file']['tmp_name'];
        $file_type = mime_content_type($file_tmp);

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Hanya file PDF atau DOC/DOCX yang diperbolehkan.";
            header("Location: upload_modul.php?mk_id=" . $mk_id);
            exit();
        }

        // Generate nama unik untuk file
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid('modul_', true) . "." . $file_ext;
        $upload_dir = "../uploads/modul/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
            // Simpan ke database
            $insert_query = "INSERT INTO modul (nama_modul, pertemuan, deskripsi, materi_file, mata_kuliah_id)
                             VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($insert_query);
            $stmt_insert->bind_param("sissi", $nama_modul, $pertemuan, $deskripsi, $new_file_name, $mk_id);

            if ($stmt_insert->execute()) {
                $_SESSION['success'] = "Modul berhasil diunggah.";
                header("Location: mataKuliah.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal menyimpan data modul.";
                header("Location: upload_modul.php?mk_id=" . $mk_id);
                exit();
            }
        } else {
            $_SESSION['error'] = "Gagal mengunggah file.";
            header("Location: upload_modul.php?mk_id=" . $mk_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "File materi harus diupload.";
        header("Location: upload_modul.php?mk_id=" . $mk_id);
        exit();
    }
}
?>

<!-- Include header template -->
<?php require_once __DIR__ . '/templates/header.php'; ?>

<!-- Main Content -->
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-gray-800 mb-4">Upload Modul untuk Mata Kuliah</h1>
    <p class="text-lg text-gray-700 mb-6">Mata Kuliah: <strong><?= htmlspecialchars($row_mk['nama_mata_kuliah']) ?></strong></p>

    <!-- Tampilkan pesan error jika ada -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Form Upload Modul -->
    <form action="" method="post" enctype="multipart/form-data"
        class="bg-white shadow-md rounded p-6 space-y-4">
        <!-- Nama Modul -->
        <div>
            <label for="nama_modul" class="block text-gray-700 font-medium mb-2">Nama Modul</label>
            <input type="text" name="nama_modul" id="nama_modul" required
                class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
        </div>

        <!-- Pertemuan -->
        <div>
            <label for="pertemuan" class="block text-gray-700 font-medium mb-2">Pertemuan ke-</label>
            <input type="number" name="pertemuan" id="pertemuan" min="1" required
                class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
        </div>

        <!-- Deskripsi -->
        <div>
            <label for="deskripsi" class="block text-gray-700 font-medium mb-2">Deskripsi</label>
            <textarea name="deskripsi" id="deskripsi" rows="4" required
                class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500"></textarea>
        </div>

        <!-- Upload File -->
        <div>
            <label for="materi_file" class="block text-gray-700 font-medium mb-2">Upload Materi (PDF/DOC/DOCX)</label>
            <input type="file" name="materi_file" id="materi_file" required
                class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-indigo-500">
        </div>

        <!-- Tombol Submit -->
        <div class="mt-6">
            <button type="submit"
                class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-200">
                Upload Modul
            </button>
        </div>
    </form>
</div>

<!-- Include footer template -->
<?php require_once __DIR__ . '/templates/footer.php'; ?>