window.chartColors = {
    red: 'rgb(255, 99, 132)',
    orange: 'rgb(255, 159, 64)',
    yellow: 'rgb(255, 205, 86)',
    green: 'rgb(75, 192, 192)',
    blue: 'rgb(54, 162, 235)',
    purple: 'rgb(153, 102, 255)',
    grey: 'rgb(231,233,237)'
};
var lineChartData = {
    labels: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
    datasets: [{
            type: 'line',
            label: 'Ventas',
            borderColor: window.chartColors.blue,
            borderWidth: 2,
            fill: false,
            data: dataVentas
        }, {
            type: 'line',
            label: 'Gastos',
            borderColor: window.chartColors.red,
            borderWidth: 2,
            fill: false,
            data: dataGastos
        }, {
            type: 'line',
            label: 'Resultado',
            borderColor: window.chartColors.green,
            borderWidth: 4,
            fill: false,
            data: dataResultado
        }]
};
var pieChartData = {
    labels: distribucionLabels,
    datasets: [{
            data: distribucionPorc,
            data2: distribucionTotal,
            backgroundColor: distribucionColor,
            hoverBackgroundColor: distribucionColor
        }]
};

window.onload = function () {
    var ctxResultado = document.getElementById("resultado-canvas").getContext("2d");
    var ctxDistribucion = document.getElementById("distribucion-canvas").getContext("2d");

    window.myLine = Chart.Line(ctxResultado, {
        data: lineChartData,
        options: {
            showAllTooltips: false,
            responsive: true,
            hoverMode: 'index',
            stacked: false,
            title: {
                display: false,
                text: 'Gráfico de Resultados por Mes'
            },
            tooltips: {
                mode: 'index',
                intersect: true
            }
        }
    });

    window.myPieChart = new Chart(ctxDistribucion, {
        type: 'pie',
        data: pieChartData,
        options: {
            showAllTooltips: false,
            cutoutPercentage: 40,
            responsive: true,
            animation: {
                animateRotate: true,
                animateScale: true
            },
            tooltips: {
                mode: 'index',
                intersect: true,
                callbacks: {
                    label: function (tooltipItem, data) {
                        var allData = data.datasets[tooltipItem.datasetIndex].data;
                        var allData2 = data.datasets[tooltipItem.datasetIndex].data2;                        
                        var tooltipLabel = data.labels[tooltipItem.index];
                        var tooltipData = allData[tooltipItem.index];
                        var tooltipData2 = allData2[tooltipItem.index];                        
                        var total = 0;
                        for (var i in allData) {
                            total += allData[i];
                        }
                        var tooltipPercentage = Math.round((tooltipData / total) * 100);
                        return tooltipLabel + ': ' + tooltipData2 + ' (' + tooltipPercentage + '%)';
                    }
                }
            }
        }
    });

    Chart.plugins.register({
        beforeRender: function (chart) {
            if (chart.config.options.showAllTooltips) {
                // create an array of tooltips
                // we can't use the chart tooltip because there is only one tooltip per chart
                chart.pluginTooltips = [];
                chart.config.data.datasets.forEach(function (dataset, i) {
                    chart.getDatasetMeta(i).data.forEach(function (sector, j) {
                        chart.pluginTooltips.push(new Chart.Tooltip({
                            _chart: chart.chart,
                            _chartInstance: chart,
                            _data: chart.data,
                            _options: chart.options.tooltips,
                            _active: [sector]
                        }, chart));
                    });
                });

                // turn off normal tooltips
                chart.options.tooltips.enabled = false;
            }
        },
        afterDraw: function (chart, easing) {
            if (chart.config.options.showAllTooltips) {
                // we don't want the permanent tooltips to animate, so don't do anything till the animation runs atleast once
                if (!chart.allTooltipsOnce) {
                    if (easing !== 1)
                        return;
                    chart.allTooltipsOnce = true;
                }

                // turn on tooltips
                chart.options.tooltips.enabled = true;
                Chart.helpers.each(chart.pluginTooltips, function (tooltip) {
                    // This line checks if the item is visible to display the tooltip
                    if (!tooltip._active[0].hidden) {
                        tooltip.initialize();
                        tooltip.update();
                        // we don't actually need this since we are not animating tooltips
                        tooltip.pivot();
                        tooltip.transition(easing).draw();
                    }
                });
                chart.options.tooltips.enabled = false;
            }
        }
    });
};
