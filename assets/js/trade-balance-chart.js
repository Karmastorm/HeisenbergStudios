document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('balance-chart');
    var dataTag = document.getElementById('balance-chart-data');
    if (!canvas || !dataTag || typeof Chart === 'undefined') {
        return;
    }

    var points = JSON.parse(dataTag.textContent);
    var styles = getComputedStyle(document.body);
    var accentColor = styles.getPropertyValue('--color-accent').trim();
    var surfaceColor = styles.getPropertyValue('--color-surface').trim();
    var textColor = styles.getPropertyValue('--color-text').trim();
    var borderColor = styles.getPropertyValue('--color-border').trim();

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(function (p) { return p.date; }),
            datasets: [{
                label: 'Balance',
                data: points.map(function (p) { return p.balance; }),
                borderColor: accentColor,
                backgroundColor: accentColor + '33',
                fill: true,
                tension: 0.2,
                pointRadius: 0,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: surfaceColor,
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: borderColor,
                    borderWidth: 1,
                    callbacks: {
                        label: function (context) {
                            return '$' + context.parsed.y.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });
                        },
                    },
                },
            },
            scales: {
                x: { grid: { color: borderColor }, ticks: { color: textColor } },
                y: { grid: { color: borderColor }, ticks: { color: textColor } },
            },
        },
    });
});
