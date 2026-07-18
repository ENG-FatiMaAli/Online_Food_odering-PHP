/**
 * FoodieApp - Chart.js Configurations
 */

const chartColors = {
    primary: '#ff6b35',
    primaryDark: '#e85d04',
    secondary: '#f7c948',
    blue: '#4e73df',
    green: '#1cc88a',
    teal: '#20c9a6',
    red: '#e74a3b',
    purple: '#6f42c1',
    orange: '#fd7e14',
    gray: '#858796'
};

const chartDarkColors = {
    primary: '#ff8c5a',
    blue: '#6c8fef',
    green: '#4dd4a6',
    teal: '#5dd9c0',
    red: '#f06c6b',
    purple: '#9b72cf',
    orange: '#ffa94d',
    gray: '#adb5bd'
};

let currentTheme = localStorage.getItem('admin_theme') || 'light';

function getColors() {
    return currentTheme === 'dark' ? chartDarkColors : chartColors;
}

function updateChartTheme(theme) {
    currentTheme = theme;
}

/* ─── Default Options ────────────────────────── */
function getDefaultOptions() {
    const colors = getColors();
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    font: { family: "'Poppins', sans-serif", size: 12 },
                    color: currentTheme === 'dark' ? '#adb5bd' : '#858796',
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                backgroundColor: currentTheme === 'dark' ? '#1a1a2e' : '#1a1a2e',
                titleFont: { family: "'Poppins', sans-serif", size: 13 },
                bodyFont: { family: "'Poppins', sans-serif", size: 12 },
                padding: 12,
                cornerRadius: 8,
                displayColors: true
            }
        },
        scales: {
            x: {
                grid: { color: currentTheme === 'dark' ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
                ticks: { font: { family: "'Poppins', sans-serif", size: 11 }, color: currentTheme === 'dark' ? '#adb5bd' : '#858796' }
            },
            y: {
                grid: { color: currentTheme === 'dark' ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)' },
                ticks: { font: { family: "'Poppins', sans-serif", size: 11 }, color: currentTheme === 'dark' ? '#adb5bd' : '#858796' }
            }
        }
    };
}

function getDoughnutOptions() {
    const opts = getDefaultOptions();
    delete opts.scales;
    return opts;
}

/* ─── Create Bar Chart ───────────────────────── */
function createBarChart(canvasId, labels, data, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    const colors = getColors();
    const barColor = color || colors.primary;
    return new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: barColor + '99',
                borderColor: barColor,
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 50
            }]
        },
        options: getDefaultOptions()
    });
}

/* ─── Create Doughnut Chart ──────────────────── */
function createDoughnutChart(canvasId, labels, data, colors) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    return new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors || [
                    '#ff6b35', '#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#6f42c1', '#20c9a6'
                ],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            ...getDoughnutOptions(),
            cutout: '65%',
            plugins: {
                ...getDoughnutOptions().plugins,
                legend: {
                    ...getDoughnutOptions().plugins.legend,
                    position: 'bottom'
                }
            }
        }
    });
}

/* ─── Create Line Chart ──────────────────────── */
function createLineChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    const colors = getColors();
    return new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            ...getDefaultOptions(),
            elements: {
                line: { tension: 0.4, borderWidth: 3 },
                point: { radius: 4, hoverRadius: 6 }
            }
        }
    });
}

/* ─── Create Horizontal Bar ──────────────────── */
function createHorizontalBar(canvasId, labels, data, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    const colors = getColors();
    const barColor = color || colors.primary;
    const opts = getDefaultOptions();
    if (opts.scales) {
        opts.indexAxis = 'y';
    }
    return new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: barColor + '80',
                borderColor: barColor,
                borderWidth: 2,
                borderRadius: 6,
                maxBarThickness: 30
            }]
        },
        options: opts
    });
}

/* ─── Gradient Fill ──────────────────────────── */
function createGradient(ctx, color1, color2) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, color1 + '80');
    gradient.addColorStop(1, color1 + '10');
    return gradient;
}
