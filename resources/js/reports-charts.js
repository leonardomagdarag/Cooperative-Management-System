import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function getColorFromClasses(classNames) {
    const el = document.createElement('span');
    el.className = classNames;
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    el.style.top = '-9999px';
    document.body.appendChild(el);
    const color = window.getComputedStyle(el).color;
    el.remove();
    return color;
}

function withAlpha(color, alpha) {
    // Supports rgb() and rgba(). Falls back to original.
    const rgb = color
        .replace('rgba(', '')
        .replace('rgb(', '')
        .replace(')', '')
        .split(',')
        .map((v) => v.trim());

    if (rgb.length < 3) return color;

    const r = Number(rgb[0]);
    const g = Number(rgb[1]);
    const b = Number(rgb[2]);

    if ([r, g, b].some((n) => Number.isNaN(n))) return color;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function buildPalette() {
    // Use Tailwind text colors to avoid hard-coding new colors.
    return {
        blue: getColorFromClasses('text-blue-600'),
        orange: getColorFromClasses('text-orange-600'),
        green: getColorFromClasses('text-green-600'),
        purple: getColorFromClasses('text-purple-600'),
        red: getColorFromClasses('text-red-600'),
        gray: getColorFromClasses('text-gray-600'),
    };
}

function renderFinancialCharts(data) {
    if (!data) return;

    const totalsCanvas = document.getElementById('financialTotalsByTypeChart');
    const inOutCanvas = document.getElementById('financialInflowOutflowChart');

    // Only render when the financial tab is visible.
    if (!totalsCanvas || !inOutCanvas) return;

    const palette = buildPalette();

    // Destroy previous charts to avoid duplicates.
    if (window.__reportsCharts?.totals) {
        window.__reportsCharts.totals.destroy();
    }
    if (window.__reportsCharts?.inout) {
        window.__reportsCharts.inout.destroy();
    }

    window.__reportsCharts = window.__reportsCharts || {};

    const typeLabels = (data.totalsByType || []).map((t) => t.label);
    const typeValues = (data.totalsByType || []).map((t) => t.value);

    const typeColors = (data.totalsByType || []).map((t) => {
        switch (t.key) {
            case 'deposit':
                return palette.blue;
            case 'withdrawal':
                return palette.orange;
            case 'loan_disbursement':
                return palette.purple;
            case 'loan_payment':
                return palette.green;
            case 'fee':
                return palette.red;
            case 'expense':
                return palette.gray;
            default:
                return palette.gray;
        }
    });

    window.__reportsCharts.totals = new Chart(totalsCanvas, {
        type: 'bar',
        data: {
            labels: typeLabels,
            datasets: [
                {
                    label: 'Total Amount',
                    data: typeValues,
                    backgroundColor: typeColors.map((c) => withAlpha(c, 0.25)),
                    borderColor: typeColors,
                    borderWidth: 1,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const v = Number(ctx.raw || 0);
                            return `₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => `₱${Number(value).toLocaleString()}`,
                    },
                    grid: { color: withAlpha(palette.gray, 0.15) },
                },
                x: {
                    grid: { display: false },
                },
            },
        },
    });

    const inflow = Number(data.inflows || 0);
    const outflow = Number(data.outflows || 0);

    window.__reportsCharts.inout = new Chart(inOutCanvas, {
        type: 'doughnut',
        data: {
            labels: ['Inflows', 'Outflows'],
            datasets: [
                {
                    data: [inflow, outflow],
                    backgroundColor: [withAlpha(palette.green, 0.25), withAlpha(palette.red, 0.25)],
                    borderColor: [palette.green, palette.red],
                    borderWidth: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const v = Number(ctx.raw || 0);
                            return `${ctx.label}: ₱${v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                        },
                    },
                },
            },
        },
    });
}

window.ReportCharts = {
    renderFinancialCharts,
};
