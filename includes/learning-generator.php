
<?php

function generateLearningMaterial(string $content): array
{
    $content = trim(strip_tags($content));
    $content = preg_replace('/\s+/u', ' ', $content);

    if (mb_strlen($content) < 30) {
        throw new RuntimeException(
            'Isi tugas terlalu pendek. Tambahkan materi terlebih dahulu.'
        );
    }

    // Memisahkan isi tugas menjadi beberapa kalimat.
    $sentences = preg_split(
        '/(?<=[.!?])\s+/u',
        $content,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $sentences = array_values(array_filter(
        array_map('trim', $sentences),
        fn($sentence) => mb_strlen($sentence) >= 15
    ));

    if (empty($sentences)) {
        $sentences = [$content];
    }

    // Ringkasan awal diambil dari beberapa kalimat pertama.
    $summary = implode(' ', array_slice($sentences, 0, 3));

    // Poin penting diambil dari kalimat-kalimat yang tersedia.
    $keyPoints = array_slice($sentences, 0, 8);

    return [
        'summary' => $summary,
        'key_points' => $keyPoints
    ];
}


/**
 * Membuat soal pilihan ganda berbasis teks materi tanpa layanan eksternal.
 * Generator memakai kalimat dan frasa yang benar-benar ada di dalam materi.
 */
function generateQuizQuestions(string $content, string $summary = '', array $keyPoints = [], int $limit = 8): array
{
    $source = trim(strip_tags(implode("\n", array_filter([$content, $summary, implode(". ", $keyPoints)]))));
    $source = preg_replace('/\s+/u', ' ', $source);
    $sentences = preg_split('/(?<=[.!?])\s+/u', $source, -1, PREG_SPLIT_NO_EMPTY);
    $sentences = array_values(array_unique(array_filter(array_map('trim', $sentences), static fn($s) => mb_strlen($s) >= 35)));
    if (count($sentences) < 2) {
        throw new RuntimeException('Isi materi belum cukup untuk membuat kuis otomatis. Tambahkan penjelasan tugas yang lebih lengkap, lalu buat ulang materi.');
    }

    // Ambil pasangan subjek-predikat jika kalimat memiliki pola definisi.
    $items = [];
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence, " \t\n\r\0\x0B-•");
        $parts = preg_split('/\b(adalah|merupakan|yaitu|yakni|berfungsi untuk|digunakan untuk|bertujuan untuk|terdiri dari|terdiri atas)\b/iu', $sentence, 2, PREG_SPLIT_DELIM_CAPTURE);
        if (count($parts) >= 3) {
            $prefix = trim($parts[0]);
            $connector = trim($parts[1]);
            $answer = trim($parts[2], " .;:");
            if (mb_strlen($prefix) >= 5 && mb_strlen($answer) >= 8 && mb_strlen($answer) <= 180) {
                $items[] = ['question' => 'Menurut materi, ' . $prefix . ' ' . mb_strtolower($connector) . ' ...?', 'answer' => $answer, 'full' => $sentence];
                continue;
            }
        }
        // Fallback: jadikan akhir kalimat sebagai bagian yang perlu dilengkapi.
        $words = preg_split('/\s+/u', $sentence);
        if (count($words) >= 8) {
            $cut = max(4, (int) floor(count($words) * 0.55));
            $prefix = implode(' ', array_slice($words, 0, $cut));
            $answer = trim(implode(' ', array_slice($words, $cut)), " .;:");
            if (mb_strlen($answer) >= 8 && mb_strlen($answer) <= 180) {
                $items[] = ['question' => 'Lengkapi pernyataan berikut berdasarkan materi: “' . $prefix . ' ...”', 'answer' => $answer, 'full' => $sentence];
            }
        }
    }

    // Hilangkan soal duplikat dan batasi jumlah soal.
    $unique = [];
    foreach ($items as $item) {
        $key = mb_strtolower($item['question']);
        $unique[$key] = $item;
    }
    $items = array_values($unique);
    if (count($items) < 2) {
        throw new RuntimeException('Sistem belum menemukan cukup kalimat yang bisa dijadikan soal. Perjelas isi tugas, kemudian perbarui materi belajar.');
    }

    $answersPool = array_values(array_unique(array_map(static fn($i) => $i['answer'], $items)));
    $questions = [];
    foreach (array_slice($items, 0, max(2, min($limit, 10))) as $index => $item) {
        $distractors = array_values(array_filter($answersPool, static fn($answer) => mb_strtolower($answer) !== mb_strtolower($item['answer'])));
        // Pilihan salah berasal dari frasa lain pada tugas yang sama.
        shuffle($distractors);
        $options = array_slice(array_merge([$item['answer']], $distractors), 0, 4);
        if (count($options) < 4) {
            // Gunakan pernyataan lain dari sumber sebagai pengecoh bila materi pendek.
            foreach ($sentences as $sentence) {
                $candidate = mb_substr(trim($sentence), 0, 180);
                if ($candidate !== '' && !in_array($candidate, $options, true)) $options[] = $candidate;
                if (count($options) >= 4) break;
            }
        }
        if (count($options) < 2) continue;
        $correctText = $item['answer'];
        shuffle($options);
        $letters = ['A', 'B', 'C', 'D'];
        $correctLetter = 'A';
        foreach ($options as $i => $option) {
            if (mb_strtolower($option) === mb_strtolower($correctText)) $correctLetter = $letters[$i];
        }
        $options = array_pad(array_slice($options, 0, 4), 4, 'Tidak disebutkan secara langsung dalam materi');
        // Pastikan huruf kunci cocok setelah opsi disiapkan.
        $correctLetter = 'A';
        foreach (array_slice($options, 0, 4) as $i => $option) {
            if (mb_strtolower($option) === mb_strtolower($correctText)) { $correctLetter = $letters[$i]; break; }
        }
        $questions[] = [
            'question' => $item['question'],
            'option_a' => $options[0], 'option_b' => $options[1],
            'option_c' => $options[2], 'option_d' => $options[3],
            'correct_answer' => $correctLetter,
            'explanation' => 'Jawaban diambil dari kalimat materi: ' . $item['full']
        ];
    }
    if (count($questions) < 2) throw new RuntimeException('Belum cukup soal yang bisa dibuat dari materi ini.');
    return $questions;
}
