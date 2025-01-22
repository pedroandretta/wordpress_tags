jQuery(document).ready(function($) {
    function removeAccents(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ç/g, 'c').replace(/Ç/g, 'C');
    }

    function updatePostCount() {
        var tag1 = $('#tag1').val();
        var tag2 = $('#tag2').val();
        var tag3 = $('#tag3').val();

        $.ajax({
            url: bct_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bct_get_post_count',
                tag1: tag1,
                tag2: tag2,
                tag3: tag3,
                nonce: bct_ajax.nonce
            },
            success: function(response) {
                var count = response.count;
                var message = '';

                if (count === 1) {
                    message = 'Existe 1 conteúdo indexado com essas Tags.';
                } else if (count > 1) {
                    message = 'Existem ' + count + ' conteúdos indexados com essas Tags.';
                } else {
                    message = 'Não existem conteúdos indexados com essa(s) Tag(s).';
                }

                $('#bct-post-count').text(message);
            }
        });
    }

    $('#tag1').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: bct_ajax.ajax_url,
                dataType: 'json',
                data: {
                    action: 'bct_get_tags',
                    term: request.term,
                    nonce: bct_ajax.nonce
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        select: function(event, ui) {
            $('#tag1').val(ui.item.value);
            $('#tag2').prop('disabled', false);
            $('#tag2').empty().append('<option value="">Selecione uma Tag</option>');
            $('#tag3').prop('disabled', true);
            $('#tag3').empty().append('<option value="">Selecione uma Tag</option>');

            // Obter as tags coocorrentes para o segundo campo
            $.ajax({
                url: bct_ajax.ajax_url,
                dataType: 'json',
                data: {
                    action: 'bct_get_cooccurring_tags',
                    selected_tags: [$('#tag1').val()],
                    nonce: bct_ajax.nonce
                },
                success: function(data) {
                    $.each(data, function(index, value) {
                        $('#tag2').append($('<option>', {
                            value: value,
                            text: value
                        }));
                    });
                }
            });

            updatePostCount();
        },
        change: function() {
            updatePostCount();
        }
    });

    $('#tag2').on('change', function() {
        var tag1 = $('#tag1').val();
        var tag2 = $('#tag2').val();

        if (tag2) {
            $('#tag3').prop('disabled', false);
            $('#tag3').empty().append('<option value="">Selecione uma Tag</option>');

            // Obter as tags coocorrentes para o terceiro campo
            $.ajax({
                url: bct_ajax.ajax_url,
                dataType: 'json',
                data: {
                    action: 'bct_get_cooccurring_tags',
                    selected_tags: [tag1, tag2],
                    nonce: bct_ajax.nonce
                },
                success: function(data) {
                    if (data.length > 0) {
                        $.each(data, function(index, value) {
                            $('#tag3').append($('<option>', {
                                value: value,
                                text: value
                            }));
                        });
                    } else {
                        $('#tag3').prop('disabled', true);
                        $('#tag3').empty().append('<option value="">Nenhuma Tag disponível</option>');
                    }
                }
            });
        } else {
            $('#tag3').prop('disabled', true);
            $('#tag3').empty().append('<option value="">Selecione uma Tag</option>');
        }

        updatePostCount();
    });

    $('#tag3').on('change', function() {
        updatePostCount();
    });

    $('#bct-formulario-busca').submit(function(e) {
        e.preventDefault();

        var tag1 = $('#tag1').val();
        var tag2 = $('#tag2').val();
        var tag3 = $('#tag3').val();

        var tags = [tag1];

        if (tag2) {
            tags.push(tag2);
        }
        if (tag3) {
            tags.push(tag3);
        }

        // Remove acentos e substitui 'ç' por 'c'
        var tags_clean = tags.map(function(tag) {
            return removeAccents(tag).toLowerCase();
        });

        // Encode cada tag separadamente e unir com '+'
        var encoded_tags = tags_clean.map(function(tag) {
            return encodeURIComponent(tag);
        });

        var url = '/index/tag/' + encoded_tags.join('+');

        window.open(url, '_blank');
    });

    // Botão "Limpar todos os campos"
    $('#bct-limpar-campos').click(function() {
        $('#tag1').val('');
        $('#tag2').prop('disabled', true).empty().append('<option value="">Selecione uma Tag</option>');
        $('#tag3').prop('disabled', true).empty().append('<option value="">Selecione uma Tag</option>');
        $('#bct-post-count').text('');
    });
});