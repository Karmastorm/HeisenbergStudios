<?php
/**
 * investments/equity_reports.php?ticker=XXXX[&raw=1]
 *
 * Sector-grouped browser for the standalone equity research reports in
 * equity_reports/ (unzipped from Equity Reports/equity_research_reports_all.zip).
 * Mirrors investments/index.php: the report list is built by scanning the
 * folder (no database involved) and each file is validated against that
 * scan before ever being streamed back out.
 *
 * Ticker/company/sector aren't in the filename here, so each file's
 * "Basic Information" table is parsed for them -- the reports were
 * generated from a couple of slightly different templates, so a few
 * fallback patterns are tried in turn.
 *
 * raw=1 streams the matching report's raw HTML (opened directly, since
 * each report is a fully self-contained page with its own head/styles)
 * after re-checking access and validating the ticker against the scanned
 * list, never trusting the query string as a filename/path directly.
 */
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
require_access('read_only');

$reportsDir = __DIR__ . '/equity_reports';

function parse_equity_report(string $path): ?array {
    $filename = basename($path);
    if (!preg_match('/^([A-Z.]+)_(equity_research_report|reference_report)\.html$/', $filename, $m)) {
        return null;
    }
    $html = file_get_contents($path);
    if ($html === false) {
        return null;
    }

    $company = $m[1];
    if (preg_match('/<h1 class="company-title">([^<]+)<\/h1>/', $html, $cm)) {
        $company = html_entity_decode($cm[1], ENT_QUOTES);
    }

    $sector = 'Other';
    if (preg_match('/<td class="k">Sector(?: \(former\))?<\/td>\s*<td class="v">([^<]+)<\/td>/', $html, $sm)
        || preg_match('/<td>Sector<\/td>\s*<td>([^<]+)<\/td>/', $html, $sm)
        || preg_match('/Sector:\s*([^|<]+?)\s*(?:&nbsp;)?\s*\|/', $html, $sm)
    ) {
        $sector = trim(html_entity_decode($sm[1], ENT_QUOTES));
        $sector = preg_replace('/\s*\(.*$/', '', $sector); // drop parenthetical asides
        if ($sector === 'Healthcare') {
            $sector = 'Health Care';
        }
    }

    return [
        'ticker'    => $m[1],
        'company'   => $company,
        'sector'    => $sector,
        'isRef'     => $m[2] === 'reference_report',
        'filename'  => $filename,
    ];
}

$reports = [];
foreach (glob($reportsDir . '/*.html') as $path) {
    $report = parse_equity_report($path);
    if ($report !== null) {
        $reports[] = $report;
    }
}

$sectors = [];
foreach ($reports as $r) {
    $sectors[$r['sector']][] = $r;
}
ksort($sectors);
foreach ($sectors as &$list) {
    usort($list, fn($a, $b) => strcmp($a['ticker'], $b['ticker']));
}
unset($list);

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

function sector_slug(string $sector): string {
    return 'sector-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($sector)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equity Sector Reports - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=8">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=8">
    <link rel="stylesheet" href="../assets/css/main.css?v=8">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content equity-reports-layout">
        <div class="equity-reports-body">
            <h1 class="page-title">Equity Sector Reports</h1>
            <p class="equity-reports-intro">
                <?php echo count($reports); ?> equity research reports, grouped by sector. Use the menu on the
                right to jump to a sector; each report opens in a new tab.
            </p>

            <?php if (empty($sectors)): ?>
                <p>No reports available yet.</p>
            <?php else: ?>
                <?php foreach ($sectors as $sector => $list): ?>
                    <section id="<?php echo htmlspecialchars(sector_slug($sector)); ?>" class="sector-group">
                        <h2 class="sector-heading"><?php echo htmlspecialchars($sector); ?></h2>
                        <ul class="report-list">
                            <?php foreach ($list as $r): ?>
                                <li>
                                    <a href="?ticker=<?php echo urlencode($r['ticker']); ?>&raw=1" target="_blank" rel="noopener">
                                        <span class="ticker-symbol"><?php echo htmlspecialchars($r['ticker']); ?></span>
                                        <span class="ticker-company"><?php echo htmlspecialchars($r['company']); ?></span>
                                        <?php if ($r['isRef']): ?>
                                            <span class="reco-badge reco-ref">REFERENCE</span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <aside class="ticker-index sector-menu">
            <h2>Sectors</h2>
            <?php if (empty($sectors)): ?>
                <p>No reports available yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($sectors as $sector => $list): ?>
                        <li>
                            <a href="#<?php echo htmlspecialchars(sector_slug($sector)); ?>">
                                <span class="ticker-symbol"><?php echo htmlspecialchars($sector); ?></span>
                                <span class="ticker-company"><?php echo count($list); ?> report<?php echo count($list) === 1 ? '' : 's'; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
