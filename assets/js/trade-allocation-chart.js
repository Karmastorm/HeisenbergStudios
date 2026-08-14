document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('allocation-ring-chart');
    var dataTag = document.getElementById('allocation-ring-data');
    var legendList = document.getElementById('allocation-ring-legend');
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
    var pcts = slices.map(function (s) {
        return total > 0 ? (s.value / total * 100) : 0;
    });

    var chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: slices.map(function (s) { return s.ticker; }),
            datasets: [{
                data: slices.map(function (s) { return s.value; }),
                backgroundColor: colors,
                borderColor: surfaceColor,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '60%',
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
                            var pct = pcts[context.dataIndex];
                            return context.label + ': $' + context.parsed.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }) + ' (' + pct.toFixed(1) + '%)';
                        },
                    },
                },
            },
        },
    });

    // Custom HTML legend -- Chart.js's built-in canvas legend can't align
    // a ticker/percentage column the way plain flex/tabular-nums CSS can,
    // and this also lets the legend sit right next to the ring instead of
    // wherever the canvas legend's own layout pass puts it.
    if (legendList) {
        slices.forEach(function (s, i) {
            var li = document.createElement('li');

            var swatch = document.createElement('span');
            swatch.className = 'legend-swatch';
            swatch.style.backgroundColor = colors[i];

            var ticker = document.createElement('span');
            ticker.className = 'legend-ticker';
            ticker.textContent = s.ticker;

            var pct = document.createElement('span');
            pct.className = 'legend-pct';
            pct.textContent = pcts[i].toFixed(1) + '%';

            li.appendChild(swatch);
            li.appendChild(ticker);
            li.appendChild(pct);
            li.addEventListener('click', function () {
                chart.toggleDataVisibility(i);
                li.classList.toggle('legend-hidden', !chart.getDataVisibility(i));
                chart.update();
            });

            legendList.appendChild(li);
        });
    }
});
