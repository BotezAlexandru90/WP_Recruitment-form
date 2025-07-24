<?php
/**
 * Plugin Name: EVE Online Recruitment Tracker
 * Description: Displays EVE Online recruitment applications and tracks member status with live filtering, summaries, and charts.
 * Version: 12.4
 * Author: Surama Badasaz
 * Author URI: https://zkillboard.com/character/91036298/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('WPINC')) { die; }

// =================================================================================
// 1. ASSET MANAGEMENT (CSS & JAVASCRIPT)
// =================================================================================

add_action('admin_enqueue_scripts', 'eve_recruitment_enqueue_assets');
/**
 * Enqueues WordPress assets and injects the plugin's CSS and JS into the page.
 */
function eve_recruitment_enqueue_assets($hook) {
    // Only load our assets on the plugin's pages
    if (strpos($hook, 'eve-recruitment') === false) { return; }
    
    // A. Enqueue WordPress's built-in assets we depend on
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('wp-jquery-ui-dialog');
    wp_enqueue_script('jquery-ui-datepicker'); // This script is loaded on all our pages

    // B. Enqueue Chart.js library from a CDN only on the tracker page where it's needed
    if ($hook === 'eve-recruitment_page_eve-recruitment-tracker') {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.2', true);
    }
    
    // C. Embed our main custom CSS
    $custom_css = <<<CSS
        /* --- General & Modern UI --- */
        .eve-viewer-wrap, .wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        .eve-header-flex { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
        /* --- Styles for the Viewer Page --- */
        .eve-viewer-wrap { max-width: 900px; }
        .eve-filter-container { background-color: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }
        #application-selector { min-width: 300px; }
        #application-details-container { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; }
        #initial-message { color: #555; font-size: 1.2em; text-align: center; padding: 40px 20px; }
        .application-card { grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .detail-box { background-color: #f9f9f9; border-left: 4px solid #72aee6; padding: 10px 15px; word-wrap: break-word; }
        .detail-box .question-title { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .detail-box .answer-text { font-size: 14px; color: #444; margin: 0; }
        /* --- Styles for the Settings Page --- */
        .form-table input.large-text { width: 99%; max-width: 450px; }
        .form-table input.small-text { width: 80px; }
        .form-table .wp-picker-container { vertical-align: middle; }
        /* --- Styles for the Member Tracker --- */
        .table-container { margin-top: 20px; }
        .wp-list-table th, .wp-list-table td { padding: 8px 10px; vertical-align: middle; }
        .wp-list-table tr:hover td { background-color: #f5f5f5; }
        .tracker-input { width: 100%; box-sizing: border-box; }
        textarea.tracker-input { min-height: 30px; resize: vertical; padding: 4px; }
        input.datepicker { max-width: 150px; }
        .save-status { font-style: italic; transition: all 0.3s ease-in-out; }
        .save-status.saving { color: #f59e0b; }
        .save-status.saved-success { color: #22c55e; font-weight: bold; }
        .save-status.saved-error { color: #ef4444; font-weight: bold; }
        .ui-datepicker-calendar { background: aliceblue; }
        /* -- Chart, Filter & Summary Styles -- */
        .chart-container { flex-grow: 1; min-width: 400px; height: auto; max-height: 250px; } /* Control chart height here */
        .summary-box { border: 1px solid #ddd; background: #fff; padding: 15px; border-radius: 4px; min-width: 280px; margin-bottom: 20px; }
        .summary-box h3 { margin: 0 0 10px; padding: 0 0 10px; border-bottom: 1px solid #eee; font-size: 16px; }
        .summary-box ul { margin: 0; padding: 0; list-style: none; }
        .summary-box li { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .summary-box .count { font-weight: 600; }
        #tracker-filters th { padding: 5px; }
        .column-filter { width: 100%; box-sizing: border-box; padding: 4px; border-radius: 3px; border: 1px solid #ccc; }
        /* -- Pagination Styles -- */
        .tablenav.bottom { display: flex; justify-content: flex-end; }
        .pagination-controls { padding: 5px 0; }
        .pagination-controls .button { margin: 0 4px; }
        .pagination-controls .displaying-num { margin-right: 10px; vertical-align: middle; }
        .updates-table td input, .widefat tfoot td input, .widefat th input, .widefat thead td input{ margin: 0 0 0 8px; padding: 0; vertical-align: text-top; padding-left: 10px;}
    CSS;
    wp_add_inline_style('wp-color-picker', $custom_css);

    // D. Embed dynamic CSS for row colors
    $status_colors = eve_recruitment_get_status_colors();
    $dynamic_css = '';
    foreach ($status_colors as $status => $color) {
        if (!empty($color)) {
            $dynamic_css .= ".status-" . esc_attr($status) . " > td { background-color: " . esc_attr($color) . " !important; }";
        }
    }
    wp_add_inline_style('wp-color-picker', $dynamic_css);

    // E. Embed our custom JavaScript
    $custom_js = <<<JS
        jQuery(document).ready(function($) {
            // Initialize color pickers on the settings page, if they exist.
            if ($('.eve-color-picker').length) {
                $('.eve-color-picker').wpColorPicker();
            }

            // --- Code for the Recruitment Viewer Page ---
            const viewerSelector = $('#application-selector');
            if (viewerSelector.length) {
                const container = $('#application-details-container');
                const initialMessage = $('#initial-message');
                viewerSelector.on('change', function() {
                    const selectedCardId = $(this).val();
                    container.find('.application-card').hide();
                    if (selectedCardId) {
                        initialMessage.hide();
                        $('#' + selectedCardId).show();
                    } else {
                        initialMessage.show();
                    }
                });
            }
            
            // --- Code for the Member Tracker Page ---
            const trackerTable = $('#member-tracker-table');
            if (trackerTable.length) {
                let currentPage = 1;
                const rowsPerPage = 25;

                // Initialize Datepickers
                $('.datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    onSelect: function(dateText, inst) {
                        $(this).trigger('change');
                        const row = $(this).closest('tr');
                        const daysCell = row.find('.days-in-corp-cell');
                        if (dateText) {
                            const startDate = new Date(dateText);
                            const endDate = new Date();
                            const diffTime = Math.abs(endDate - startDate);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                            daysCell.text(diffDays);
                        } else {
                            daysCell.text('N/A');
                        }
                    }
                });

                // AJAX Save Logic (debounced)
                let saveTimer;
                trackerTable.on('input change', '.tracker-input', function() {
                    clearTimeout(saveTimer);
                    const inputElement = $(this);
                    saveTimer = setTimeout(function() {
                        const row = inputElement.closest('tr');
                        const statusCell = row.find('.save-status');
                        const key = inputElement.data('key');
                        const field = inputElement.data('field');
                        const value = inputElement.val();
                        
                        if (field === 'status') {
                           row.removeClass (function (i, c) { return (c.match (/(^|\s)status-\S+/g) || []).join(' '); }).addClass('status-' + value);
                        }

                        statusCell.text('Saving...').removeClass('saved-error').addClass('saving');
                        $.post(eve_ajax_obj.ajax_url, {
                            action: 'eve_save_tracker_data',
                            nonce: eve_ajax_obj.nonce,
                            key: key,
                            field: field,
                            value: value
                        }, function(response) {
                            if (response.success) {
                                statusCell.text('Saved!').removeClass('saving').addClass('saved-success');
                            } else {
                                statusCell.text('Error!').removeClass('saving').addClass('saved-error');
                            }
                            setTimeout(function() { statusCell.fadeOut(400, function() { $(this).text('').show(); }); }, 3000);
                        });
                    }, 500);
                });

                // --- PAGINATION & FILTERING LOGIC (REVISED) ---
                function updatePagination() {
                    const filteredRows = trackerTable.find('tbody tr:not(.is-filtered-out)');
                    const totalRows = filteredRows.length;
                    const numPages = Math.ceil(totalRows / rowsPerPage);

                    currentPage = Math.max(1, Math.min(currentPage, numPages));

                    const paginationControls = $('.pagination-controls');
                    if (numPages > 1) {
                        const startRange = (currentPage - 1) * rowsPerPage + 1;
                        let endRange = currentPage * rowsPerPage;
                        if (endRange > totalRows) { endRange = totalRows; }
                        
                        paginationControls.find('.displaying-num').text('Displaying ' + startRange + '-' + endRange + ' of ' + totalRows);
                        paginationControls.find('#prev-page').prop('disabled', currentPage === 1);
                        paginationControls.find('#next-page').prop('disabled', currentPage === numPages);
                        paginationControls.show();
                    } else {
                        paginationControls.hide();
                    }

                    trackerTable.find('tbody tr').hide();
                    filteredRows.slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).show();
                }
                
                function filterTable() {
                    const filters = {};
                    $('#tracker-filters .column-filter').each(function() {
                        const colIndex = $(this).data('column');
                        const value = $(this).val().toLowerCase();
                        if (value) { filters[colIndex] = value; }
                    });

                    trackerTable.find('tbody tr').each(function() {
                        let isVisible = true;
                        const row = $(this);
                        for (const colIndex in filters) {
                            let cellContent;
                            const cell = row.find('td').eq(colIndex);

                            if (cell.find('select').length > 0) {
                                cellContent = cell.find('select').val();
                                if(cellContent !== filters[colIndex]) {
                                    isVisible = false;
                                    break;
                                }
                            } else {
                                cellContent = cell.text().toLowerCase();
                                if (!cellContent.includes(filters[colIndex])) {
                                    isVisible = false;
                                    break;
                                }
                            }
                        }
                        row.toggleClass('is-filtered-out', !isVisible);
                    });
                    
                    currentPage = 1;
                    updatePagination();
                }

                function setupPagination() {
                    const totalRows = trackerTable.find('tbody tr').length;
                    if (totalRows > rowsPerPage) {
                        const controls = '<span class="displaying-num"></span>' +
                            '<button type="button" class="button" id="prev-page">Previous</button>' +
                            '<button type="button" class="button" id="next-page">Next</button>';
                        $('.pagination-controls').html(controls);

                        $('#prev-page').on('click', function() {
                            if (currentPage > 1) {
                                currentPage--;
                                updatePagination();
                            }
                        });

                        $('#next-page').on('click', function() {
                             currentPage++;
                             updatePagination();
                        });
                    }
                }
                
                $('#tracker-filters').on('keyup change', '.column-filter', filterTable);
                
                $('#clear-filters-btn').on('click', function() {
                    $('#tracker-filters .column-filter').val('').trigger('change');
                });

                // Initial setup
                setupPagination();
                updatePagination();
            }

            // --- Chart.js Initialization ---
            const chartCanvas = $('#recruitmentChart');
            if (chartCanvas.length && typeof Chart !== 'undefined' && typeof eve_tracker_obj !== 'undefined') {
                const ctx = chartCanvas[0].getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: eve_tracker_obj.chartData.labels,
                        datasets: [{
                            label: 'Monthly Applications',
                            data: eve_tracker_obj.chartData.data,
                            backgroundColor: 'rgba(114, 174, 230, 0.6)',
                            borderColor: 'rgba(114, 174, 230, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Applications' }, ticks: { stepSize: 1 } }, x: { title: { display: false } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { title: function() { return ''; }, label: function(context) { return context.dataset.label + ': ' + context.raw; } } } }
                    }
                });
            }
        });
    JS;
    wp_add_inline_script('jquery-ui-datepicker', $custom_js);
    
    // F. Pass data to JS
    wp_localize_script('jquery-ui-datepicker', 'eve_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('eve_tracker_nonce')
    ]);
}

// =================================================================================
// 2. CORE PLUGIN LOGIC
// =================================================================================

add_action('admin_menu', 'eve_recruitment_add_admin_menu');
add_action('admin_init', 'eve_recruitment_settings_init');
add_action('wp_ajax_eve_save_tracker_data', 'eve_ajax_save_tracker_data_callback');

function eve_recruitment_get_data() {
    $default_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSpEO1BLZ_n1NWyBRCyh_or6ubR8QSpTM4IvELc--mGkXR0fUcnTI_CTg_5QXPOTeZBoOZaxZraUQfc/pub?gid=1277970709&single=true&output=csv';
    $csv_url = get_option('eve_recruitment_csv_url', $default_url);
    if (empty($csv_url)) {
        return new WP_Error('no_url', 'The Google Sheet CSV URL is not configured. Please go to the Settings page to add it.');
    }
    $csv_url_no_cache = add_query_arg('t', time(), $csv_url);
    $response = wp_remote_get($csv_url_no_cache, ['timeout' => 20]);
    if (is_wp_error($response)) { return $response; }
    $csv_data = wp_remote_retrieve_body($response);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $csv_data);
    rewind($stream);
    $header = fgetcsv($stream);
    if ($header === false) { fclose($stream); return new WP_Error('empty_data', 'Could not read header row. The sheet might be empty or the URL is incorrect.'); }
    $applications = [];
    while (($row = fgetcsv($stream)) !== false) { $applications[] = $row; }
    fclose($stream);
    return ['header' => $header, 'applications' => array_reverse($applications)];
}

function eve_recruitment_add_admin_menu() {
    add_menu_page('EVE Recruitment Tracker', 'EVE Recruitment Tracker', 'edit_others_posts', 'eve-recruitment-viewer', 'eve_recruitment_display_page_html', 'dashicons-groups', 6);
    add_submenu_page('eve-recruitment-viewer', 'Recruitment Viewer', 'Recruitment Viewer', 'edit_others_posts', 'eve-recruitment-viewer', 'eve_recruitment_display_page_html');
    add_submenu_page('eve-recruitment-viewer', 'Member Tracker', 'Member Tracker', 'edit_others_posts', 'eve-recruitment-tracker', 'eve_recruitment_tracker_page_html');
    add_submenu_page('eve-recruitment-viewer', 'EVE Recruitment Settings', 'Settings', 'manage_options', 'eve-recruitment-settings', 'eve_recruitment_settings_page_html');
}

function eve_recruitment_display_page_html() {
    $data = eve_recruitment_get_data();
    if (is_wp_error($data)) {
        echo '<div class="wrap"><h1>EVE Online Recruitment Applications</h1><div class="notice notice-error"><p>' . esc_html($data->get_error_message()) . '</p></div></div>';
        return;
    }
    $filter_column_index = get_option('eve_recruitment_filter_column', 2) - 1;
    $display_order_str = get_option('eve_recruitment_display_order', '');
    $display_order_arr = [];
    if (!empty($display_order_str)) {
        $display_order_arr = array_map('intval', array_map('trim', explode(',', $display_order_str)));
    } else {
        $column_count = get_option('eve_recruitment_column_count', 15);
        $display_order_arr = range(1, $column_count);
    }
    ?>
    <div class="wrap eve-viewer-wrap">
        <h1>EVE Online Recruitment Applications</h1>
        <div class="eve-filter-container">
             <label for="application-selector">Select Applicant:</label>
            <select id="application-selector">
                <option value="">-- Select an Application (<?php echo count($data['applications']); ?> Total) --</option>
                <?php foreach ($data['applications'] as $index => $app):
                    if (!is_array($app)) continue;
                    $filter_text = isset($app[$filter_column_index]) && !empty($app[$filter_column_index]) ? $app[$filter_column_index] : 'Unnamed Application';
                    $unique_id = 'app-id-' . $index;
                ?>
                    <option value="<?php echo esc_attr($unique_id); ?>"><?php echo esc_html($filter_text); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="application-details-container">
            <?php foreach ($data['applications'] as $index => $app):
                 if (!is_array($app)) continue;
                $unique_id = 'app-id-' . $index;
            ?>
            <div class="application-card" id="<?php echo esc_attr($unique_id); ?>" style="display: none;">
                <?php foreach ($display_order_arr as $col_num):
                    $col_index = $col_num - 1;
                    if (isset($data['header'][$col_index])):
                        $question = $data['header'][$col_index];
                        $answer = isset($app[$col_index]) ? $app[$col_index] : '';
                        
                        // Generate a URL-friendly slug from the question title for use as a CSS class
                        $question_class = 'q-' . sanitize_title($question);
                ?>
                <div class="detail-box col-<?php echo esc_attr($col_num); ?> <?php echo esc_attr($question_class); ?>">
                    <strong class="question-title"><?php echo esc_html($question); ?></strong>
                    <p class="answer-text"><?php echo nl2br(esc_html($answer)); ?></p>
                </div>
                <?php endif; endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div id="initial-message">Please select an application from the dropdown to view details.</div>
        </div>
    </div>
    <?php
}

function eve_recruitment_prepare_chart_data($applications) {
    $monthly_counts = [];
    $month_labels = [];
    
    $current_date = new DateTime('first day of this month');
    for ($i = 0; $i < 12; $i++) {
        $key = $current_date->format('Y-m');
        $monthly_counts[$key] = 0;
        $month_labels[$key] = $current_date->format('M Y');
        $current_date->modify('-1 month');
    }
    
    foreach ($applications as $app) {
        if (!empty($app[0])) {
            try {
                $app_date = new DateTime($app[0]);
                $key = $app_date->format('Y-m');
                if (array_key_exists($key, $monthly_counts)) {
                    $monthly_counts[$key]++;
                }
            } catch (Exception $e) { /* Ignore invalid date formats */ }
        }
    }
    
    return [
        'labels' => array_reverse(array_values($month_labels)),
        'data'   => array_reverse(array_values($monthly_counts))
    ];
}

function eve_recruitment_tracker_page_html() {
    $sheet_data = eve_recruitment_get_data();
    if (is_wp_error($sheet_data)) {
        echo '<div class="wrap"><h1>Member Tracker</h1><div class="notice notice-error"><p>' . esc_html($sheet_data->get_error_message()) . '</p></div></div>';
        return;
    }
    $tracker_data = get_option('eve_tracker_data', []);
    $status_options = eve_recruitment_get_status_options();
    $rank_options = ['trial' => 'Trial', 'scout' => 'Scout'];

    // Calculate Summaries
    $summary_counts = ['joined' => 0, 'declined' => 0, 'scout' => 0, 'trial' => 0];
    foreach ($sheet_data['applications'] as $app) {
        if (!is_array($app) || !isset($app[0])) continue;
        $applicant_name = isset($app[1]) ? $app[1] : 'N/A';
        $applicant_key = md5(trim($app[0] . $applicant_name));
        $saved = isset($tracker_data[$applicant_key]) ? $tracker_data[$applicant_key] : [];
        $status = isset($saved['status']) ? $saved['status'] : 'pending_check';
        $rank = isset($saved['rank']) ? $saved['rank'] : 'trial';

        if ($status === 'joined') $summary_counts['joined']++;
        if ($status === 'declined') $summary_counts['declined']++;
        if ($rank === 'scout') $summary_counts['scout']++;
        if ($rank === 'trial') $summary_counts['trial']++;
    }
    $chart_data = eve_recruitment_prepare_chart_data($sheet_data['applications']);
    wp_localize_script('jquery-ui-datepicker', 'eve_tracker_obj', ['chartData' => $chart_data]);
    ?>
    <div class="wrap">
        <div class="eve-header-flex">
            <div>
                <h1>Member Tracker</h1>
                <p>Track the status and progress of applicants. Use the filters to search the table.</p>
            </div>
            <div class="chart-container">
                <canvas id="recruitmentChart"></canvas>
            </div>
            <div class="summary-box">
                <h3>Summary</h3>
                <ul>
                    <li><span>Status: Joined</span> <span class="count"><?php echo $summary_counts['joined']; ?></span></li>
                    <li><span>Status: Declined</span> <span class="count"><?php echo $summary_counts['declined']; ?></span></li>
                    <li style="border-top: 1px dashed #ccc; margin-top: 5px; padding-top: 5px;"><span>Rank: Trial</span> <span class="count"><?php echo $summary_counts['trial']; ?></span></li>
                    <li><span>Rank: Scout</span> <span class="count"><?php echo $summary_counts['scout']; ?></span></li>
                </ul>
            </div>
        </div>
        
        <div class="table-container">
            <table class="wp-list-table widefat fixed striped" id="member-tracker-table">
                <thead>
                    <tr>
                        <th>Applicant Name</th>
                        <th>Discord ID</th>
                        <th>Recruitment Status</th>
                        <th>Join Date</th>
                        <th>Days in Corp</th>
                        <th>Rank</th>
                        <th>Notes</th>
                        <th style="width: 80px;">Status</th>
                    </tr>
                    <tr id="tracker-filters">
                        <th><input type="text" class="column-filter" data-column="0" placeholder="Filter names..."></th>
                        <th><input type="text" class="column-filter" data-column="1" placeholder="Filter nations..."></th>
                        <th>
                            <select class="column-filter" data-column="2">
                                <option value="">All Statuses</option>
                                <?php foreach ($status_options as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th></th>
                        <th></th>
                        <th>
                            <select class="column-filter" data-column="5">
                                <option value="">All Ranks</option>
                                <?php foreach ($rank_options as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input type="text" class="column-filter" data-column="6" placeholder="Filter notes..."></th>
                        <th><button type="button" id="clear-filters-btn" class="button button-secondary">Clear</button></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($sheet_data['applications'] as $app) {
                        if (!is_array($app) || !isset($app[0])) continue;
                        $applicant_name = isset($app[3]) ? $app[3] : 'N/A';
                        $applicant_key = md5(trim($app[0] . $applicant_name));
                        $saved = isset($tracker_data[$applicant_key]) ? $tracker_data[$applicant_key] : [];
                        $status = isset($saved['status']) ? $saved['status'] : 'pending_check';
                        $join_date = isset($saved['join_date']) ? $saved['join_date'] : '';
                        $rank = isset($saved['rank']) ? $saved['rank'] : 'trial';
                        $notes = isset($saved['notes']) ? $saved['notes'] : '';
                        $days_in_corp = 'N/A';
                        if (!empty($join_date)) {
                            try {
                                $start = new DateTime($join_date); $end = new DateTime();
                                $days_in_corp = $end->diff($start)->days;
                            } catch (Exception $e) { $days_in_corp = 'Invalid Date'; }
                        }
                    ?>
                    <tr class="status-<?php echo esc_attr($status); ?>">
                        <td><?php echo esc_html($applicant_name); ?></td>
                        <td><?php echo isset($app[2]) ? esc_html($app[2]) : 'N/A'; ?></td>
                        <td>
                            <select class="tracker-input" data-key="<?php echo esc_attr($applicant_key); ?>" data-field="status">
                                <?php foreach ($status_options as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($status, $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" class="tracker-input datepicker" data-key="<?php echo esc_attr($applicant_key); ?>" data-field="join_date" value="<?php echo esc_attr($join_date); ?>" placeholder="Select a date..."></td>
                        <td class="days-in-corp-cell"><?php echo esc_html($days_in_corp); ?></td>
                        <td>
                            <select class="tracker-input" data-key="<?php echo esc_attr($applicant_key); ?>" data-field="rank">
                                <?php foreach ($rank_options as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($rank, $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><textarea class="tracker-input" data-key="<?php echo esc_attr($applicant_key); ?>" data-field="notes" rows="1"><?php echo esc_textarea($notes); ?></textarea></td>
                        <td><span class="save-status"></span></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="tablenav bottom">
                <div class="pagination-controls"></div>
            </div>
        </div>
    </div>
    <?php
}

function eve_ajax_save_tracker_data_callback() {
    check_ajax_referer('eve_tracker_nonce', 'nonce');
    if (!current_user_can('edit_others_posts')) {
        wp_send_json_error(['message' => 'Permission denied.']); return;
    }
    $key   = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
    $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
    $value = isset($_POST['value']) ? ($field === 'notes' ? sanitize_textarea_field(stripslashes($_POST['value'])) : sanitize_text_field(stripslashes($_POST['value']))) : '';
    if (empty($key) || empty($field)) {
        wp_send_json_error(['message' => 'Missing key or field.']); return;
    }
    $tracker_data = get_option('eve_tracker_data', []);
    $tracker_data[$key][$field] = $value;
    update_option('eve_tracker_data', $tracker_data);
    wp_send_json_success(['message' => 'Saved!']);
}

// =================================================================================
// 3. SETTINGS PAGE (LOGIC & HTML)
// =================================================================================

function eve_recruitment_get_status_options() {
    return [
        'pending_check'   => 'Pending Check',
        'joined'          => 'Joined',
        'declined'        => 'Declined',
        'kicked'          => 'Kicked',
        'other_corp'      => 'Went to a different corp',
		'left_corp'      => 'Left corp',
		'left_discord'      => 'Left Discord',
        'not_responding'  => 'Not responding',
    ];
}

function eve_recruitment_get_status_colors() {
    $statuses = eve_recruitment_get_status_options();
    $colors = [];
    $defaults = [
        'pending_check'   => '#EBF4FF', 'joined'          => '#E6F4EA',
        'declined'        => '#FDECEC', 'kicked'          => '#FFEBE6', 'left_discord'          => '#F5F5F5',
        'other_corp'      => '#F5F5F5', 'not_responding'  => '#FFF9E6', 'left_corp'        => '#F5F5F5'
    ];
    foreach ($statuses as $slug => $label) {
        $colors[$slug] = get_option('eve_color_' . $slug, $defaults[$slug]);
    }
    return $colors;
}

function eve_recruitment_settings_init() {
    register_setting('eve_recruitment_options', 'eve_recruitment_csv_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    register_setting('eve_recruitment_options', 'eve_recruitment_column_count', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 15]);
    register_setting('eve_recruitment_options', 'eve_recruitment_filter_column', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 2]);
    register_setting('eve_recruitment_options', 'eve_recruitment_display_order', ['type' => 'string', 'sanitize_callback' => 'eve_recruitment_sanitize_order_string', 'default' => '']);
    
    $statuses = eve_recruitment_get_status_options();
    foreach ($statuses as $slug => $label) {
        register_setting('eve_recruitment_options', 'eve_color_' . $slug, ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color']);
    }

    add_settings_section('eve_recruitment_source_section', 'Data Source Settings', null, 'eve-recruitment-settings');
    add_settings_field('csv_url', 'Google Sheet CSV URL', 'eve_recruitment_field_callback_url', 'eve-recruitment-settings', 'eve_recruitment_source_section');
    
    add_settings_section('eve_recruitment_display_section', 'Display Settings', null, 'eve-recruitment-settings');
    add_settings_field('filter_column', 'Filter Column Number', 'eve_recruitment_field_callback_numeric', 'eve-recruitment-settings', 'eve_recruitment_display_section', ['name' => 'eve_recruitment_filter_column']);
    add_settings_field('column_count', 'Number of Columns to Display (Simple Mode)', 'eve_recruitment_field_callback_numeric', 'eve-recruitment-settings', 'eve_recruitment_display_section', ['name' => 'eve_recruitment_column_count']);
    add_settings_field('display_order', 'Display Order (Advanced Mode)', 'eve_recruitment_field_callback_text', 'eve-recruitment-settings', 'eve_recruitment_display_section', ['name' => 'eve_recruitment_display_order']);
    
    add_settings_section('eve_recruitment_colors_section', 'Status Color Settings', null, 'eve-recruitment-settings');
    foreach ($statuses as $slug => $label) {
        add_settings_field('eve_color_' . $slug, $label, 'eve_recruitment_field_callback_color', 'eve-recruitment-settings', 'eve_recruitment_colors_section', ['slug' => $slug]);
    }
}

function eve_recruitment_sanitize_order_string($input) { return preg_replace('/[^0-9,]/', '', $input); }

function eve_recruitment_field_callback_url() {
    $default_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSpEO1BLZ_n1NWyBRCyh_or6ubR8QSpTM4IvELc--mGkXR0fUcnTI_CTg_5QXPOTeZBoOZaxZraUQfc/pub?gid=1277970709&single=true&output=csv';
    $value = get_option('eve_recruitment_csv_url', $default_url);
    echo '<input type="url" name="eve_recruitment_csv_url" value="' . esc_attr($value) . '" class="large-text" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" />';
    echo '<p class="description">Publish your Google Sheet to the web as a CSV and paste the generated link here.</p>';
}

function eve_recruitment_field_callback_numeric($args) {
    $defaults = ['eve_recruitment_column_count' => 15, 'eve_recruitment_filter_column' => 2];
    $descriptions = [
        'eve_recruitment_column_count' => 'This is ignored if "Display Order" is used below.',
        'eve_recruitment_filter_column' => 'The column to use for the applicant filter dropdown (e.g., 2 for the second column).'
    ];
    $value = get_option($args['name'], $defaults[$args['name']]);
    echo '<input type="number" name="' . esc_attr($args['name']) . '" value="' . esc_attr($value) . '" min="1" class="small-text" />';
    echo '<p class="description">' . wp_kses_post($descriptions[$args['name']]) . '</p>';
}

function eve_recruitment_field_callback_text($args) {
    $value = get_option('eve_recruitment_display_order', '');
    echo '<input type="text" name="eve_recruitment_display_order" value="' . esc_attr($value) . '" class="large-text" />';
    echo '<p class="description">Enter column numbers in your desired order, separated by commas (e.g., <strong>2, 3, 15, 1, 5</strong>). This overrides Simple Mode. Leave blank to disable.</p>';
}

function eve_recruitment_field_callback_color($args) {
    $slug = $args['slug'];
    $value = get_option('eve_color_' . $slug, eve_recruitment_get_status_colors()[$slug]);
    echo '<input type="text" name="eve_color_' . esc_attr($slug) . '" value="' . esc_attr($value) . '" class="eve-color-picker" />';
}

function eve_recruitment_settings_page_html() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Use these settings to control the data source and appearance of the recruitment tools.</p>
        <form action="options.php" method="post">
            <?php
                settings_fields('eve_recruitment_options');
                do_settings_sections('eve-recruitment-settings');
                submit_button('Save All Settings');
            ?>
        </form>
    </div>
    <?php
}