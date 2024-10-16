<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://wpspeedtestpro.com
 * @since      1.0.0
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin/partials
 */

 function convert_to_seconds($milliseconds) {
    return number_format($milliseconds / 1000, 2) . 's';
}

function get_color_class($metric, $value) {
    $thresholds = [
        'performance_score' => ['green' => 90, 'amber' => 50],
        'first_contentful_paint' => ['green' => 1.8, 'amber' => 3],
        'speed_index' => ['green' => 3.4, 'amber' => 5.8],
        'largest_contentful_paint' => ['green' => 2.5, 'amber' => 4],
        'total_blocking_time' => ['green' => 200, 'amber' => 600],
        'cumulative_layout_shift' => ['green' => 0.1, 'amber' => 0.25]
    ];

    if ($metric === 'performance_score') {
        if ($value >= $thresholds[$metric]['green']) return 'green';
        if ($value >= $thresholds[$metric]['amber']) return 'amber';
        return 'red';
    } else {
        if ($value <= $thresholds[$metric]['green']) return 'green';
        if ($value <= $thresholds[$metric]['amber']) return 'amber';
        return 'red';
    }
}

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h1>WP Speed Test Pro - Page Speed Testing</h1>

    <?php if (!isset($data)) { echo "There was an error fetching data."; return; } ?>

    <div id="speedvitals-credits-info">
        <h3>Account Credits</h3>
        <p>Lighthouse Credits: <?php echo esc_html($data['credits']['lighthouse']['available_credits']); ?></p>
        <!-- Not used at the moment  <p>TTFB Credits: <?php echo esc_html($data['credits']['ttfb']['available_credits']); ?></p> -->
         <p>Next Refill: <?php echo wp_date('Y-m-d H:i:s', $data['credits']['credits_refill_date']); ?></p>
        <?php if ($data['credits']['lighthouse']['available_credits'] <= 0) : ?>
            <p style="color: red;">You have no Lighthouse credits remaining. Please <a href="https://speedvitals/pricing" target="_blank">purchase more credits or wait until the next refill</a>.</p>
        <?php endif; ?>
        </div>
    <h2>Run a New Test</h2>
    <form id="speedvitals-test-form">
        <div class="speedvitals-form-container">
            <div class="speedvitals-form-row">
                <div class="speedvitals-form-column">
                    <label for="speedvitals-url">URL to Test</label>
                    <select id="speedvitals-url" name="url">
                        <?php foreach ($data['pages_and_posts'] as $id => $title) : ?>
                            <option value="<?php echo esc_url(get_permalink($id)); ?>"><?php echo esc_html($title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="speedvitals-form-column">
                    <label for="speedvitals-location">Test Location</label>
                    <select id="speedvitals-location" name="location">
                        <?php foreach ($data['locations'] as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="speedvitals-form-column">
                    <label for="speedvitals-device">Device</label>
                    <select id="speedvitals-device" name="device">
                        <?php foreach ($data['devices'] as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="speedvitals-form-column">
                    <label for="speedvitals-frequency">Test Frequency</label>
                    <select id="speedvitals-frequency" name="frequency">
                        <?php foreach ($data['frequencies'] as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="speedvitals-form-column submit-column">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Run Test">
                </div>
            </div>
        </div>
    </form>

    <div id="speedvitals-test-status" style="display: none;">
        <h3>Test Status</h3>
        <p id="speedvitals-status-message"></p>
        <div id="speedvitals-loading-gif" style="display: none;">
            <img src="<?php echo esc_url(admin_url('images/loading.gif')); ?>" alt="Loading">
        </div>
    </div>

    <h2>Test Results</h2>
    <table class="speedvitals-table wp-list-table widefat fixed striped">
        <thead>
            <tr>
            <th title="Unique identifier for each test">Test ID</th>
            <th title="The URL that was tested">URL</th>
            <th title="The device type used for the test">Device</th>
            <th title="The location from which the test was run">Location</th>
            <th title="The date and time when the test was conducted">Date</th>
            <th title="Overall performance score (0-100). Higher is better.">Performance Score</th>
            <th title="First Contentful Paint (FCP): Time when the first text or image is painted. Lower is better.">FCP (s)</th>
            <th title="Speed Index (SI): How quickly the contents of a page are visibly populated. Lower is better.">SI (s)</th>
            <th title="Largest Contentful Paint (LCP): Time when the largest text or image is painted. Lower is better.">LCP (s)</th>
            <th title="Total Blocking Time (TBT): Sum of all time periods between FCP and Time to Interactive, when task length exceeded 50ms. Lower is better.">TBT (s)</th>
            <th title="Cumulative Layout Shift (CLS): Measures visual stability. Lower is better.">CLS</th>
            <th title="Actions you can take on this test result">Actions</th>
            </tr>
        </thead>
        <tbody id="speedvitals-results-body"><?php
            // In your table rendering loop:
            foreach ($data['test_results'] as $result) : ?>
                <tr id="test-row-<?php echo esc_attr($result['test_id']); ?>">
                    <td><?php echo esc_html($result['test_id']); ?></td>
                    <td><?php echo esc_url($result['url']); ?></td>
                    <td><?php echo esc_html($result['device']); ?></td>
                    <td><?php echo esc_html($result['location']); ?></td>
                    <td><?php echo esc_html($result['test_date']); ?></td>
                    <?php if (empty($result['performance_score'])) : ?>
                        <td>Pending Results...</td>
                        <td></td><td></td><td></td><td></td><td></td>
                    <?php else : ?>
                        <td class="<?php echo get_color_class('performance_score', $result['performance_score']); ?>"><?php echo esc_html($result['performance_score']); ?></td>
                        <td class="<?php echo get_color_class('first_contentful_paint', $result['first_contentful_paint'] / 1000); ?>"><?php echo esc_html(convert_to_seconds($result['first_contentful_paint'])); ?></td>
                        <td class="<?php echo get_color_class('speed_index', $result['speed_index'] / 1000); ?>"><?php echo esc_html(convert_to_seconds($result['speed_index'])); ?></td>
                        <td class="<?php echo get_color_class('largest_contentful_paint', $result['largest_contentful_paint'] / 1000); ?>"><?php echo esc_html(convert_to_seconds($result['largest_contentful_paint'])); ?></td>
                        <td class="<?php echo get_color_class('total_blocking_time', $result['total_blocking_time']); ?>"><?php echo esc_html(convert_to_seconds($result['total_blocking_time'])); ?></td>
                        <td class="<?php echo get_color_class('cumulative_layout_shift', $result['cumulative_layout_shift']); ?>"><?php echo esc_html(number_format($result['cumulative_layout_shift'], 2)); ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if (empty($result['performance_score'])) : ?>
                            Report Pending
                        <?php else : ?>
                            <a href="<?php echo esc_url($result['report_url']); ?>" target="_blank">View Report</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Scheduled Tests</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>URL</th>
                <th>Device</th>
                <th>Location</th>
                <th>Frequency</th>
                <th>Next Run</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="speedvitals-scheduled-tests-body">
            <?php foreach ($data['scheduled_tests'] as $test) : ?>
                <tr>
                    <td><?php echo esc_html($test['id']); ?></td>
                    <td><?php echo esc_url($test['url']); ?></td>
                    <td><?php echo esc_html($test['device']); ?></td>
                    <td><?php echo esc_html($test['location']); ?></td>
                    <td><?php echo esc_html($test['frequency']); ?></td>
                    <td><?php echo esc_html($test['next_run']); ?></td>
                    <td>
                        <button class="button button-secondary speedvitals-cancel-scheduled-test" data-id="<?php echo esc_attr($test['id']); ?>">Cancel</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Maintenance</h2>
    <form id="speedvitals-delete-old-results-form">
        <p>
            <label for="speedvitals-delete-days">Delete results older than:</label>
            <input type="number" id="speedvitals-delete-days" name="days" min="1" value="30">
            days
        </p>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-secondary" value="Delete Old Results">
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {


    var probeInterval;
    var isProbing = false;

    function startProbing() {
        if (!isProbing) {
            isProbing = true;
            probeInterval = setInterval(probeForUpdates, 5000); // Probe every 5 seconds
        }
    }

    function stopProbing() {
        if (isProbing) {
            clearInterval(probeInterval);
            isProbing = false;
        }
    }

    function probeForUpdates() {
        $.ajax({
            url: wpspeedtestpro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'speedvitals_probe_for_updates',
                nonce: wpspeedtestpro_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateResultsTable(response.data.updated_tests);
                }
            },
            error: function() {
                console.log('Error probing for updates');
            }
        });
    }

    function updateResultsTable(updatedTests) {

        function convertToSeconds(milliseconds) {
        return (milliseconds / 1000).toFixed(2) + 's';
    }

    function getColorClass(metric, value) {
        const thresholds = {
            performance_score: { green: 90, amber: 50 },
            first_contentful_paint: { green: 1.8, amber: 3 },
            speed_index: { green: 3.4, amber: 5.8 },
            largest_contentful_paint: { green: 2.5, amber: 4 },
            total_blocking_time: { green: 200, amber: 600 },
            cumulative_layout_shift: { green: 0.1, amber: 0.25 }
        };

        if (metric === 'performance_score') {
            if (value >= thresholds[metric].green) return 'green';
            if (value >= thresholds[metric].amber) return 'amber';
            return 'red';
        } else {
            if (value <= thresholds[metric].green) return 'green';
            if (value <= thresholds[metric].amber) return 'amber';
            return 'red';
        }
    }


    updatedTests.forEach(function(test) {
        var row = $('#test-row-' + test.id);
        if (row.length) {
            // Update existing row
            row.find('td:eq(0)').text(test.id);
            row.find('td:eq(1)').text(test.url);
            row.find('td:eq(2)').text(test.device);
            row.find('td:eq(3)').text(test.location);
            row.find('td:eq(4)').text(new Date(test.created_at).toLocaleString());
            if (test.metrics && typeof test.metrics.performance_score !== 'undefined') {
                row.find('td:eq(5)').text(test.metrics.performance_score)
                    .removeClass('red amber green')
                    .addClass(getColorClass('performance_score', test.metrics.performance_score));
                row.find('td:eq(6)').text(convertToSeconds(test.metrics.first_contentful_paint))
                    .removeClass('red amber green')
                    .addClass(getColorClass('first_contentful_paint', test.metrics.first_contentful_paint / 1000))
                    .show();
                row.find('td:eq(7)').text(convertToSeconds(test.metrics.speed_index))
                    .removeClass('red amber green')
                    .addClass(getColorClass('speed_index', test.metrics.speed_index / 1000))
                    .show();
                row.find('td:eq(8)').text(convertToSeconds(test.metrics.largest_contentful_paint))
                    .removeClass('red amber green')
                    .addClass(getColorClass('largest_contentful_paint', test.metrics.largest_contentful_paint / 1000))
                    .show();
                row.find('td:eq(9)').text(convertToSeconds(test.metrics.total_blocking_time))
                    .removeClass('red amber green')
                    .addClass(getColorClass('total_blocking_time', test.metrics.total_blocking_time))
                    .show();
                row.find('td:eq(10)').text(test.metrics.cumulative_layout_shift.toFixed(2))
                    .removeClass('red amber green')
                    .addClass(getColorClass('cumulative_layout_shift', test.metrics.cumulative_layout_shift))
                    .show();
                row.find('td:eq(11) a').attr('href', test.report_url);
                row.find('td:eq(11) a').attr('href', test.report_url).text('View Report');
                    } else { // Test in progress    
            row.find('td:eq(5)').text('Test in progress....');
            }

        } else {
            // Add new row to the table
            var newRow = '<tr id="test-row-' + test.id + '">' +
                '<td>' + test.id + '</td>' +
                '<td>' + test.url + '</td>' +
                '<td>' + test.device + '</td>' +
                '<td>' + test.location + '</td>' +
                '<td>' + new Date(test.created_at).toLocaleString() + '</td>';
                if (test.metrics && typeof test.metrics.performance_score !== 'undefined') {
                    newRow +=
                    '<td>' + (test.metrics ? (test.metrics.performance_score || 'N/A') : 'N/A') + '</td>' +
                    '<td>' + (test.metrics ? (test.metrics.first_contentful_paint ? convertToSeconds(test.metrics.first_contentful_paint) : 'N/A') : 'N/A') + '</td>' +
                    '<td>' + (test.metrics ? (test.metrics.speed_index ? convertToSeconds(test.metrics.speed_index) : 'N/A') : 'N/A') + '</td>' +
                  '<td>' + (test.metrics ? (test.metrics.largest_contentful_paint ? convertToSeconds(test.metrics.largest_contentful_paint) : 'N/A') : 'N/A') + '</td>' +
                 '<td>' + (test.metrics ? (test.metrics.total_blocking_time ? convertToSeconds(test.metrics.total_blocking_time) : 'N/A') : 'N/A') + '</td>' +
                 '<td>' + (test.metrics ? (test.metrics.cumulative_layout_shift ? test.metrics.cumulative_layout_shift.toFixed(2) : 'N/A') : 'N/A') + '</td>';
                    } else {
                    newRow += '<td>Test in progress.....</td>';
                    newRow += "<td></td><td></td><td></td><td></td><td></td>";
                }
                newRow += '</tr>';
            $('#speedvitals-results-body').prepend(newRow);
        }
    });
}

$('#speedvitals-test-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serializeArray();
    
    $('#speedvitals-test-status').show();
    $('#speedvitals-loading-gif').show();
    $('#speedvitals-status-message').text('Initiating test...');

    var data = {
        action: 'speedvitals_run_test',
        nonce: wpspeedtestpro_ajax.nonce
    };

    // Convert the serialized array to an object
    $.each(formData, function(i, field) {
        data[field.name] = field.value;
    });

    $.ajax({
        url: wpspeedtestpro_ajax.ajax_url,
        type: 'POST',
        data: data,
        success: function(response) {
            if (response.success && response.data && response.data.id) {
                $('#speedvitals-status-message').text('Test initiated successfully. Results will update automatically.');
                
                var testData = response.data;
                
                // Add a new row for the initiated test
                var newRow = '<tr id="test-row-' + testData.id + '">' +
                    '<td>' + testData.id + '</td>' +
                    '<td>' + testData.url + '</td>' +
                    '<td>' + testData.device + '</td>' +
                    '<td>' + testData.location + '</td>' +
                    '<td>' + new Date(testData.created_at).toLocaleString() + '</td>' +
                    '<td>Test in progress...</td>' +
                    '<td></td>' + '<td></td>' + '<td></td>' + '<td></td>' + '<td></td>' +
                    '<td><a href="#" target="_blank">Report Pending</a></td>' +
                    '</tr>';
                $('#speedvitals-results-body').prepend(newRow);
                
                startProbing(); // Start probing for updates
            } else {
                $('#speedvitals-status-message').text('Error: Unable to initiate test. Please try again.');
                $('#speedvitals-loading-gif').hide();
            }
        },
        error: function() {
            $('#speedvitals-status-message').text('An error occurred. Please try again.');
            $('#speedvitals-loading-gif').hide();
        }
    });
});

    // Start probing when the page loads
    startProbing();

    // Stop probing when the user leaves the page
    $(window).on('beforeunload', function() {
        stopProbing();
    });


    function checkTestStatus(testId) {
        $.ajax({
            url: wpspeedtestpro_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'speedvitals_get_test_status',
                nonce: wpspeedtestpro_ajax.nonce,
                test_id: testId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.status === 'completed') {
                        $('#speedvitals-status-message').text('Test completed successfully!');
                        $('#speedvitals-loading-gif').hide();
                        // Refresh the results table
                        location.reload();
                    } else {
                        $('#speedvitals-status-message').text('Test in progress: ' + response.data.status);
                        setTimeout(function() {
                            checkTestStatus(testId);
                        }, 10000);
                    }
                } else {
                    $('#speedvitals-status-message').text('Error: ' + response.data);
                    $('#speedvitals-loading-gif').hide();
                }
            },
            error: function() {
                $('#speedvitals-status-message').text('An error occurred while checking test status.');
                $('#speedvitals-loading-gif').hide();
            }
        });
    }

    $('.speedvitals-cancel-scheduled-test').on('click', function() {
        var scheduleId = $(this).data('id');
        if (confirm('Are you sure you want to cancel this scheduled test?')) {
            $.ajax({
                url: wpspeedtestpro_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'speedvitals_cancel_scheduled_test',
                    nonce: wpspeedtestpro_ajax.nonce,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Scheduled test cancelled successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });

    $('#speedvitals-delete-old-results-form').on('submit', function(e) {
        e.preventDefault();
        var days = $('#speedvitals-delete-days').val();
        
        if (confirm('Are you sure you want to delete results older than ' + days + ' days?')) {
            $.ajax({
                url: wpspeedtestpro_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'speedvitals_delete_old_results',
                    nonce: wpspeedtestpro_ajax.nonce,
                    days: days
                },
                success: function(response) {
                    if (response.success) {
                        alert('Old results deleted successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });
});
</script>