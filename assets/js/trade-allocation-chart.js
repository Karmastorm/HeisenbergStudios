document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('allocation-ring-chart');
    var dataTag = document.getElementById('allocation-ring-data');
    if (!canvas || !dataTag || typeof Chart === 'undefined') {
        return;
    }

    var slices = JSON.parse(dataTag.textContent);
    var styles = getComputedStyle(document.body);
    var surfaceColor = styles.getPropertyValue('--color-surface').trim();
    var textColor = styles.getPropertyValue('--color-text').trim();
    var borderColor = styles.getPropertyValue('--color-border').trim();
    var categoryColors = [1, 2, 3, 4, 5, 6, 7, 8].map(function (n) {
        return styles.getPropertyValue('--chart-cat-' + n).trim();
    });
    var otherColor = styles.getPropertyValue('--chart-cat-other').trim();

    var total = slices.reduce(function (sum, s) { return sum + s.value; }, 0);
    var colors = slices.map(function (s, i) {
        return s.ticker === 'Other' ? otherColor : categoryColors[i % categoryColors.length];
    });
    var pctLabels = slices.map(function (s) {
        var pct = total > 0 ? (s.value / total * 100) : 0;
        return s.ticker + ' — ' + pct.toFixed(1) + '%';
    });

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: pctLabels,
            datasets: [{
                data: slices.map(function (s) { return s.value; }),
                backgroundColor: colors,
                borderColor: surfaceColor,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: { color: textColor },
                },
                tooltip: {
                    backgroundColor: surfaceColor,
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: borderColor,
                    borderWidth: 1,
                    callbacks: {
                        label: function (context) {
                            return context.label + ': $' + context.parsed.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });
                        },
                    },
                },
            },
        },
    });
});
