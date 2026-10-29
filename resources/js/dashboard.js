const initializedPages = new WeakSet();

function drawChart(chart) {
    const canvas = chart.querySelector('[data-chart-canvas]');
    if (!canvas) return;

    let data;
    try {
        data = JSON.parse(chart.dataset.chart ?? '[]');
    } catch {
        return;
    }

    const bounds = canvas.getBoundingClientRect();
    if (bounds.width === 0 || bounds.height === 0) return;

    const scale = window.devicePixelRatio || 1;
    canvas.width = Math.round(bounds.width * scale);
    canvas.height = Math.round(bounds.height * scale);
    const context = canvas.getContext('2d');
    context.setTransform(scale, 0, 0, scale, 0, 0);
    context.clearRect(0, 0, bounds.width, bounds.height);

    const horizontal = chart.dataset.dashboardChart === 'categories';
    if (horizontal) {
        drawHorizontalBars(context, data, bounds.width, bounds.height);
    } else if (chart.dataset.dashboardChart === 'pie') {
        drawDonut(context, data, bounds.width, bounds.height, chart.dataset.centerLabel || 'Total value');
    } else if (chart.dataset.dashboardChart === 'comparison') {
        drawComparison(context, data, bounds.width, bounds.height);
    } else {
        drawVerticalBars(context, data, bounds.width, bounds.height);
    }
}

function drawDonut(context, data, width, height, centerLabel) {
    const palette = ['#245332', '#3f7d3a', '#79a96f', '#c9a227', '#8cae91', '#55735c'];
    const slices = data.map((item, index) => ({ ...item, color: palette[index % palette.length] }))
        .filter(({ total }) => Number(total) > 0);
    const total = slices.reduce((sum, slice) => sum + Number(slice.total), 0);
    if (total <= 0) return;

    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) * 0.43;
    const innerRadius = radius * 0.58;
    let angle = -Math.PI / 2;
    slices.forEach(({ total: value, color }) => {
        const endAngle = angle + (Number(value) / total) * Math.PI * 2;
        context.beginPath();
        context.arc(centerX, centerY, radius, angle, endAngle);
        context.arc(centerX, centerY, innerRadius, endAngle, angle, true);
        context.closePath();
        context.fillStyle = color;
        context.fill();
        angle = endAngle;
    });
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillStyle = '#666';
    context.font = '11px sans-serif';
    context.fillText(centerLabel, centerX, centerY - 9);
    context.fillStyle = '#245332';
    context.font = 'bold 13px sans-serif';
    context.fillText(`₱${total.toLocaleString(undefined, { maximumFractionDigits: 0 })}`, centerX, centerY + 10);
}

function drawComparison(context, data, width, height) {
    const revenue = data.revenue || [];
    const expenses = data.expenses || [];
    const padding = { top: 12, right: 10, bottom: 28, left: 48 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const values = [...revenue, ...expenses].map((row) => Number(row.total));
    const max = Math.max(1, ...values);
    const min = Math.min(0, ...values);
    const range = max - min;
    const zeroY = padding.top + max / range * chartHeight;
    const slotWidth = chartWidth / Math.max(1, revenue.length);
    context.font = '11px sans-serif';
    context.textAlign = 'right';
    context.textBaseline = 'middle';
    context.fillStyle = '#666';
    for (let row = 0; row <= 3; row++) {
        const value = max - range * row / 3;
        const y = padding.top + (max - value) / range * chartHeight;
        context.strokeStyle = '#e5e7eb';
        context.beginPath();
        context.moveTo(padding.left, y);
        context.lineTo(width - padding.right, y);
        context.stroke();
        context.fillText(value.toLocaleString(undefined, { maximumFractionDigits: 0 }), padding.left - 7, y);
    }
    revenue.forEach(({ label, total }, index) => {
        const barWidth = Math.min(16, slotWidth * 0.32);
        const x = padding.left + index * slotWidth + slotWidth / 2;
        const revenueValue = Number(total);
        const expenseValue = Number(expenses[index]?.total || 0);
        const revenueHeight = Math.abs(revenueValue / range * chartHeight);
        const expenseHeight = Math.abs(expenseValue / range * chartHeight);
        context.fillStyle = '#3f7d3a';
        context.fillRect(x - barWidth, revenueValue >= 0 ? zeroY - revenueHeight : zeroY, barWidth - 1, revenueHeight);
        context.fillStyle = '#c9a227';
        context.fillRect(x + 1, expenseValue >= 0 ? zeroY - expenseHeight : zeroY, barWidth - 1, expenseHeight);
        context.textAlign = 'center';
        context.textBaseline = 'top';
        context.fillStyle = '#666';
        context.fillText(label, x, height - padding.bottom + 8);
    });
}

function drawVerticalBars(context, data, width, height) {
    const padding = { top: 10, right: 10, bottom: 28, left: 44 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const max = Math.max(1, ...data.map(({ total }) => Number(total)));
    const slotWidth = chartWidth / Math.max(1, data.length);

    context.font = '11px sans-serif';
    context.textAlign = 'right';
    context.textBaseline = 'middle';
    context.fillStyle = '#666';
    for (let row = 0; row <= 3; row++) {
        const y = padding.top + chartHeight * row / 3;
        const value = max * (1 - row / 3);
        context.strokeStyle = '#e5e7eb';
        context.beginPath();
        context.moveTo(padding.left, y);
        context.lineTo(width - padding.right, y);
        context.stroke();
        context.fillText(value.toLocaleString(undefined, { maximumFractionDigits: 0 }), padding.left - 7, y);
    }

    data.forEach(({ label, total }, index) => {
        const barWidth = slotWidth * 0.58;
        const barHeight = chartHeight * Number(total) / max;
        const x = padding.left + index * slotWidth + (slotWidth - barWidth) / 2;
        const y = padding.top + chartHeight - barHeight;
        context.fillStyle = '#3f7d3a';
        context.fillRect(x, y, barWidth, barHeight);
        context.textAlign = 'center';
        context.textBaseline = 'top';
        context.fillStyle = '#666';
        context.fillText(label, x + barWidth / 2, height - padding.bottom + 8);
    });
}

function drawHorizontalBars(context, data, width, height) {
    const padding = { top: 8, right: 12, bottom: 8, left: Math.min(width * 0.42, 150) };
    const chartWidth = width - padding.left - padding.right - 86;
    const slotHeight = (height - padding.top - padding.bottom) / Math.max(1, data.length);
    const max = Math.max(1, ...data.map(({ total }) => Number(total)));

    context.font = '12px sans-serif';
    context.textBaseline = 'middle';
    data.forEach(({ label, total }, index) => {
        const y = padding.top + index * slotHeight;
        const barHeight = Math.min(20, slotHeight * 0.62);
        const barWidth = Math.max(0, chartWidth) * Number(total) / max;
        context.textAlign = 'right';
        context.fillStyle = '#4d4d4d';
        context.fillText(label, padding.left - 8, y + slotHeight / 2, padding.left - 14);
        context.fillStyle = '#1f5b2c';
        context.fillRect(padding.left, y + (slotHeight - barHeight) / 2, barWidth, barHeight);
        context.textAlign = 'left';
        context.fillStyle = '#666';
        context.fillText(`₱${Number(total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`, padding.left + barWidth + 6, y + slotHeight / 2);
    });
}

function initializeChartPage(page, updateEvent) {
    if (initializedPages.has(page)) return;
    initializedPages.add(page);

    const render = () => page.querySelectorAll('[data-dashboard-chart]').forEach(drawChart);
    render();

    if (window.ResizeObserver) {
        const observer = new ResizeObserver(render);
        observer.observe(page);
        page.addEventListener('livewire:removed', () => observer.disconnect(), { once: true });
    }

    const mutationObserver = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.type === 'attributes' && mutation.attributeName === 'data-chart')) render();
    });
    mutationObserver.observe(page, { subtree: true, attributes: true, attributeFilter: ['data-chart'] });
    page.addEventListener('livewire:removed', () => mutationObserver.disconnect(), { once: true });

    if (window.Livewire) {
        window.Livewire.on(updateEvent, ({ sales }) => {
            const chart = page.querySelector('[data-dashboard-chart="sales"]');
            if (!chart || !Array.isArray(sales)) return;
            chart.dataset.chart = JSON.stringify(sales);
            drawChart(chart);
        });
    }
}

function initializeDashboard(page) {
    initializeChartPage(page, 'dashboard-chart-update');
}

function initializeReports(page) {
    initializeChartPage(page, 'reports-chart-update');
}

function initializePages() {
    document.querySelectorAll('[data-dashboard-page]').forEach(initializeDashboard);
    document.querySelectorAll('[data-reports-page]').forEach(initializeReports);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePages, { once: true });
} else {
    initializePages();
}

document.addEventListener('livewire:navigated', initializePages);
