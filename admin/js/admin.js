jQuery(document).ready(function($) {
    // Handle content type selection
    $('#content_type').on('change', function() {
        var customPostTypeRow = $('.custom-post-type-row');
        if ($(this).val() === 'custom') {
            customPostTypeRow.show();
        } else {
            customPostTypeRow.hide();
        }
    });

    // Media uploader for room logo
    $('#upload_logo').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var input = $('#logoURL');
        
        var mediaUploader = wp.media({
            title: 'Select Logo',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
        });

        mediaUploader.open();
    });

    // Media uploader for textures
    $('.upload-texture').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var target = button.data('target');
        var input = $('input[name="wp_arrival_space_room_config[' + target + ']"]');
        
        var mediaUploader = wp.media({
            title: 'Select Texture',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
        });

        mediaUploader.open();
    });

    // Media uploader for gate logo
    $('.upload-logo').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var input = button.prev('input');
        
        var mediaUploader = wp.media({
            title: 'Select Logo',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
        });

        mediaUploader.open();
    });

    // Post search functionality
    var searchTimeout;
    $('#gate_search').on('input', function() {
        clearTimeout(searchTimeout);
        var search = $(this).val();
        
        if (search.length < 2) {
            $('#search_results').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: wpArrivalSpace.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'arrival_space_search_posts',
                    nonce: wpArrivalSpace.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var html = '<ul>';
                        results.forEach(function(post) {
                            html += '<li data-id="' + post.id + '" data-type="' + post.type + '">' +
                                   '<strong>' + post.title + '</strong> (' + post.type + ')<br>' +
                                   '<small>' + post.url + '</small>' +
                                   '</li>';
                        });
                        html += '</ul>';
                        $('#search_results').html(html);
                    }
                }
            });
        }, 300);
    });

    // Handle post selection
    $('#search_results').on('click', 'li', function() {
        var postId = $(this).data('id');
        var postType = $(this).data('type');
        var postTitle = $(this).find('strong').text();
        
        // Add new gate
        var gateIndex = $('.gate-item').length;
        var gateHtml = createGateItem(gateIndex, {
            title: postTitle,
            post_id: postId,
            post_type: postType,
            post_title: postTitle
        });
        
        $('.gates-list').append(gateHtml);
        $('#search_results').empty();
        $('#gate_search').val('');
    });

    // Create gate item HTML
    function createGateItem(index, gate) {
        return '<div class="gate-item" data-index="' + index + '">' +
               '<div class="gate-header">' +
               '<h4>' + (gate.title || 'Gate ' + (index + 1)) + '</h4>' +
               '<div class="gate-actions">' +
               '<button type="button" class="button edit-gate">Edit</button>' +
               '<button type="button" class="button remove-gate">Remove</button>' +
               '</div>' +
               '</div>' +
               '<div class="gate-content">' +
               '<p>' + (gate.description || '') + '</p>' +
               '<p class="gate-post">' +
               'Post: ' + gate.post_title + ' (' + gate.post_type + ')' +
               '</p>' +
               '</div>' +
               '</div>';
    }

    // Edit gate
    $('.gates-list').on('click', '.edit-gate', function() {
        var gateItem = $(this).closest('.gate-item');
        var index = gateItem.data('index');
        var gate = getGateConfig(index);
        
        // Populate form
        $('#gate_title').val(gate.title || '');
        $('#gate_description').val(gate.description || '');
        // Add other fields as needed
        
        // Store current editing index
        $('#gate_config_form').data('editing-index', index);
    });

    // Remove gate
    $('.gates-list').on('click', '.remove-gate', function() {
        if (confirm('Are you sure you want to remove this gate?')) {
            $(this).closest('.gate-item').remove();
            updateGatesConfig();
        }
    });

    // Get gate configuration
    function getGateConfig(index) {
        var gates = getGatesConfig();
        return gates[index] || {};
    }

    // Get all gates configuration
    function getGatesConfig() {
        try {
            return JSON.parse($('#gates_json').val() || '{"gates":[]}').gates;
        } catch (e) {
            return [];
        }
    }

    // Update gates configuration
    function updateGatesConfig() {
        var gates = [];
        $('.gate-item').each(function() {
            var index = $(this).data('index');
            var gate = getGateConfig(index);
            gates.push(gate);
        });
        
        $('#gates_json').val(JSON.stringify({gates: gates}, null, 2));
    }

    // Generate config button
    $('#generate-config').on('click', function() {
        var roomConfig = {};
        var gatesConfig = {};
        
        // Get room config
        try {
            roomConfig = JSON.parse($('#room_json').val() || '{}');
        } catch (e) {
            console.error('Invalid room JSON:', e);
        }
        
        // Get gates config
        try {
            gatesConfig = JSON.parse($('#gates_json').val() || '{"gates":[]}');
        } catch (e) {
            console.error('Invalid gates JSON:', e);
        }

        // Update preview
        $('#config-preview').text(JSON.stringify({
            room: roomConfig,
            gates: gatesConfig
        }, null, 2));
    });

    // Copy to clipboard button
    $('#copy-config').on('click', function() {
        var configText = $('#config-preview').text();
        
        // Create temporary textarea
        var textarea = document.createElement('textarea');
        textarea.value = configText;
        document.body.appendChild(textarea);
        
        // Select and copy
        textarea.select();
        document.execCommand('copy');
        
        // Remove temporary textarea
        document.body.removeChild(textarea);
        
        // Show feedback
        var button = $(this);
        var originalText = button.text();
        button.text('Copied!');
        
        setTimeout(function() {
            button.text(originalText);
        }, 2000);
    });

    // Save gate configuration
    $('#gate_config_form').on('submit', function(e) {
        e.preventDefault();
        var index = $(this).data('editing-index');
        var gate = {
            title: $('#gate_title').val(),
            description: $('#gate_description').val(),
            // Add other fields as needed
        };
        
        // Update gate in the list
        var gateItem = $('.gate-item[data-index="' + index + '"]');
        gateItem.find('h4').text(gate.title);
        gateItem.find('.gate-content p:first').text(gate.description);
        
        // Update JSON
        updateGatesConfig();
        
        // Clear form
        $(this).trigger('reset');
        $(this).removeData('editing-index');
    });

    // Initialize content type visibility
    $('#content_type').trigger('change');
}); 