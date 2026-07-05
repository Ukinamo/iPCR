<script setup>
import { Chart, registerables } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

const props = defineProps({
    type: {
        type: String,
        required: true,
    },
    data: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        default: () => ({}),
    },
});

const canvasRef = ref(null);
let chartInstance = null;

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                boxWidth: 12,
                boxHeight: 12,
                usePointStyle: true,
                font: { size: 11 },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 0 },
        },
        y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 10 } },
            grid: { color: 'rgba(148, 163, 184, 0.2)' },
        },
    },
};

function renderChart() {
    if (!canvasRef.value || !props.data) {
        return;
    }

    chartInstance?.destroy();

    const mergedOptions = {
        ...defaultOptions,
        ...props.options,
        plugins: {
            ...defaultOptions.plugins,
            ...(props.options.plugins ?? {}),
        },
    };

    if (props.type === 'doughnut' || props.type === 'pie') {
        delete mergedOptions.scales;
    }

    chartInstance = new Chart(canvasRef.value, {
        type: props.type,
        data: props.data,
        options: mergedOptions,
    });
}

onMounted(renderChart);
watch(() => [props.type, props.data, props.options], renderChart, { deep: true });
onBeforeUnmount(() => {
    chartInstance?.destroy();
    chartInstance = null;
});
</script>

<template>
    <div class="h-64 w-full">
        <canvas ref="canvasRef" />
    </div>
</template>
