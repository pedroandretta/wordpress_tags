jQuery(document).ready(function($) {
    var availableTags = [];
    $.ajax({
        url: tfd_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'tfd_get_tags'
        },
        success: function(response) {
            if(response.success) {
                availableTags = response.data;
                $('.tfd-dashboard input[type="text"]').autocomplete({
                    source: availableTags
                });
            }
        }
    });

    $('#tfd-search-form').on('submit', function(e) {
        e.preventDefault();
        // Processar a pesquisa e gerar os gráficos
        var search_terms = [];
        var group_terms = [];
        $('.tfd-input-group').each(function(index) {
            var term = $(this).find('input[type="text"]').val().trim();
            var group = $(this).find('input[type="checkbox"]').is(':checked');

            search_terms.push(term);
            if (group) {
                group_terms.push(index); // Índice corrigido
            }
        });

        if (search_terms.filter(function(term){ return term !== ''; }).length === 0) {
            alert('Por favor, insira pelo menos uma TAG ou expressão.');
            return;
        }

        $.ajax({
            url: tfd_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'tfd_get_frequency_data',
                search_terms: search_terms,
                group_terms: group_terms
            },
            success: function(response) {
                if(response.success) {
                    // Gerar os gráficos com os dados retornados
                    generateCharts(response.data);
                } else {
                    alert('Erro ao obter os dados.');
                }
            }
        });
    });

    // Botão "Limpar todos os campos"
    $('#tfd-clear-button').on('click', function() {
        $('.tfd-dashboard input[type="text"]').val('');
        $('.tfd-dashboard input[type="checkbox"]').prop('checked', false);
        $('#tfd-chart-container').empty();
    });

    function generateCharts(data) {
        $('#tfd-chart-container').empty(); // Limpa os gráficos anteriores

        // Gráfico Mensal
        var labelsSet = new Set();
        data.forEach(function(item) {
            Object.keys(item.frequency.monthly).forEach(function(date) {
                labelsSet.add(date);
            });
        });
        var labels = Array.from(labelsSet);
        labels.sort();

        var datasets = [];

        data.forEach(function(item, index) {
            var termData = item.frequency.monthly;
            var dataPoints = [];
            labels.forEach(function(label) {
                dataPoints.push(termData[label] ? termData[label] : 0);
            });
            datasets.push({
                label: item.term,
                data: dataPoints,
                backgroundColor: getRandomColor()
            });
        });

        var ctx = document.createElement('canvas');
        $('#tfd-chart-container').append('<h3>Frequência Mensal</h3>');
        $('#tfd-chart-container').append(ctx);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Frequência de Uso Mensal ao Longo do Tempo'
                },
                scales: {
                    xAxes: [{
                        stacked: true,
                        type: 'time',
                        time: {
                            parser: 'YYYY-MM',
                            unit: 'month',
                            displayFormats: {
                                month: 'MMM YYYY'
                            }
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Tempo'
                        }
                    }],
                    yAxes: [{
                        stacked: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Frequência'
                        },
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                }
            }
        });

        // Gráfico Anual
        var yearlyLabelsSet = new Set();
        data.forEach(function(item) {
            Object.keys(item.frequency.yearly).forEach(function(year) {
                yearlyLabelsSet.add(year);
            });
        });
        var yearlyLabels = Array.from(yearlyLabelsSet);
        yearlyLabels.sort();

        var yearlyDatasets = [];

        data.forEach(function(item, index) {
            var termData = item.frequency.yearly;
            var dataPoints = [];
            yearlyLabels.forEach(function(label) {
                dataPoints.push(termData[label] ? termData[label] : 0);
            });
            yearlyDatasets.push({
                label: item.term,
                data: dataPoints,
                backgroundColor: getRandomColor()
            });
        });

        var ctxYearly = document.createElement('canvas');
        $('#tfd-chart-container').append('<h3>Frequência Anual</h3>');
        $('#tfd-chart-container').append(ctxYearly);

        new Chart(ctxYearly, {
            type: 'bar',
            data: {
                labels: yearlyLabels,
                datasets: yearlyDatasets
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Frequência de Uso Anual'
                },
                scales: {
                    xAxes: [{
                        stacked: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Ano'
                        }
                    }],
                    yAxes: [{
                        stacked: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Frequência'
                        },
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                }
            }
        });
    }

    function getRandomColor() {
        var letters = '0123456789ABCDEF';
        var color = '#';
        for (var i = 0; i < 6; i++ ) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }
});