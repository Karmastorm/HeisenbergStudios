<?php
/**
 * investments/index.php?ticker=XXXX[&raw=1]
 *
 * Browser for the fundamental-analysis reports in investment_fundamentals/.
 * The ticker list is built by scanning that folder (no database involved) --
 * this index is static and specific to this section only.
 *
 * raw=1 streams the matching report's raw HTML (for the iframe below) after
 * re-checking access and validating the ticker against the scanned list,
 * never trusting the query string as a filename/path directly.
 */
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
require_access('restricted');

$reportsDir = __DIR__ . '/investment_fundamentals';

$reports = [];
foreach (glob($reportsDir . '/*.html') as $path) {
    $filename = basename($path);
    if (preg_match('/^\d{8} - ([A-Z.]+) (.+?) - fundamental_analysis\.html$/', $filename, $m)) {
        $reports[] = [
            'ticker'   => $m[1],
            'company'  => $m[2],
            'filename' => $filename,
        ];
    }
}
usort($reports, fn($a, $b) => strcmp($a['ticker'], $b['ticker']));

$selectedFile = null;
$selectedTicker = $_GET['ticker'] ?? null;
if ($selectedTicker !== null) {
    foreach ($reports as $r) {
        if ($r['ticker'] === $selectedTicker) {
            $selectedFile = $r['filename'];
            break;
        }
    }
}

if (isset($_GET['raw'])) {
    if ($selectedFile === null) {
        http_response_code(404);
        exit('Report not found.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Frame-Options: SAMEORIGIN');
    readfile($reportsDir . '/' . $selectedFile);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Fundamentals - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content investments-layout">
        <aside class="ticker-index">
            <h2>Tickers</h2>
            <?php if (empty($reports)): ?>
                <p>No reports available yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($reports as $r): ?>
                        <li>
                            <a class="<?php echo $r['filename'] === $selectedFile ? 'active' : ''; ?>"
                               href="?ticker=<?php echo urlencode($r['ticker']); ?>">
                                <span class="ticker-symbol"><?php echo htmlspecialchars($r['ticker']); ?></span>
                                <span class="ticker-company"><?php echo htmlspecialchars($r['company']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>

        <section class="ticker-viewer">
            <?php if ($selectedFile): ?>
                <iframe
                    src="?ticker=<?php echo urlencode($selectedTicker); ?>&raw=1"
                    title="<?php echo htmlspecialchars($selectedTicker); ?> fundamental analysis report">
                </iframe>
            <?php else: ?>
                <p class="ticker-viewer-empty">Select a ticker from the left to view its fundamental analysis report.</p>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
