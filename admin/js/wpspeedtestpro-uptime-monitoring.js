(function($) {
    'use strict';

    const MIN_DATAPOINTS = 5;
    const REFRESH_INTERVAL = 1 * 60 * 1000; // 5 minutes in milliseconds

    $(document).ready(function() {
        if ($('#uptime-monitors-data').length) {
            uptimerobot_loadMonitorData();
            // Start automatic refreshing
            setInterval(uptimerobot_loadMonitorData, REFRESH_INTERVAL);
        }

        $('#setup-monitors').on('click', uptimerobot_setupMonitors);
        $('#refresh-monitors').on('click', uptimerobot_loadMonitorData);
        $('#delete-monitors').on('click', uptimerobot_deleteMonitors);
        $('#recreate-monitors').on('click', uptimerobot_recreateMonitors);
    });

    function uptimerobot_loadMonitorData() {
        $('#uptime-monitors-data').addClass('loading');
        $('#uptime-monitors-data .spinner').show();
        $('#uptime-monitors-data p').text('Loading monitor data...');

        $.ajax({
            url: wpspeedtestpro_uptime.ajax_url,
            type: 'POST',
            data: {
                action: 'wpspeedtestpro_uptimerobot_get_monitor_data',
                nonce: wpspeedtestpro_uptime.nonce
            },
            success: function(response) {
                $('#uptime-monitors-data').removeClass('loading');
                $('#uptime-monitors-data .spinner').hide();
                $('#uptime-monitors-data p').text(''); // Clear the loading text

                if (response.success) {
                    uptimerobot_updateMonitorDisplay(response.data);
                } else {
                    $('#uptime-monitors-data p').text('Error loading monitor data: ' + response.data);
                }
            },
            error: function() {
                $('#uptime-monitors-data').removeClass('loading');
                $('#uptime-monitors-data .spinner').hide();
                $('#uptime-monitors-data p').text('Error loading monitor data. Please try again.');
            }
        });
    }

    function uptimerobot_updateMonitorDisplay(data) {
        if (Array.isArray(data)) {
            let pingData = null;
            let cronData = null;

            data.forEach(function(monitor) {
                if (monitor.friendly_name.includes('Ping')) {
                    pingData = monitor;
                    uptimerobot_updateLogs('ping-monitor-logs', monitor.logs);
                } else if (monitor.friendly_name.includes('Cron')) {
                    cronData = monitor;
                    uptimerobot_updateLogs('cron-monitor-logs', monitor.logs);
                }
            });

            if (pingData && cronData) {
                uptimerobot_updateCombinedGraph('combined-monitor-graph', pingData, cronData);
            } else {
                console.error('Missing ping or cron data');
            }
        } else {
            console.error('Unexpected data structure received:', data);
        }
    }

    function uptimerobot_updateCombinedGraph(canvasId, pingData, cronData) {
        const $canvas = $('#' + canvasId);
        const $container = $canvas.parent();
    
        if (!pingData.response_times || !cronData.response_times || 
            (pingData.response_times.length < MIN_DATAPOINTS && cronData.response_times.length < MIN_DATAPOINTS)) {
            $canvas.hide();
            let $message = $container.find('.not-enough-data');
            if ($message.length === 0) {
                $message = $('<p class="not-enough-data">').html(
                    'Not enough data to display the graph yet. Please wait for more data to be collected.<br>' +
                    'Ping Average Response Time: ' + pingData.average_response_time + ' ms<br>' +
                    'Cron Average Response Time: ' + cronData.average_response_time + ' ms'
                );
                $container.append($message);
            } else {
                $message.html(
                    'Not enough data to display the graph yet. Please wait for more data to be collected.<br>' +
                    'Ping Average Response Time: ' + pingData.average_response_time + ' ms<br>' +
                    'Cron Average Response Time: ' + cronData.average_response_time + ' ms'
                );
            }
            return;
        }
    
        $canvas.show();
        $container.find('.not-enough-data').remove();
    
        // Combine and sort all timestamps
        const allTimestamps = [...pingData.response_times, ...cronData.response_times]
            .map(item => item.datetime)
            .sort((a, b) => a - b);
    
        // Create datasets
        const pingDataset = {
            label: 'Ping Response Time (ms)',
            data: allTimestamps.map(timestamp => {
                const dataPoint = pingData.response_times.find(item => item.datetime === timestamp);
                return dataPoint ? dataPoint.value : null;
            }),
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1,
            fill: false,
            spanGaps: true
        };
    
        const cronDataset = {
            label: 'Cron Response Time (ms)',
            data: allTimestamps.map(timestamp => {
                const dataPoint = cronData.response_times.find(item => item.datetime === timestamp);
                return dataPoint ? dataPoint.value : null;
            }),
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1,
            fill: false,
            spanGaps: true
        };
    
        var ctx = $canvas[0].getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: allTimestamps.map(timestamp => new Date(timestamp * 1000).toLocaleString()),
                datasets: [pingDataset, cronDataset]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'category',
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Response Time (ms)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }
    function uptimerobot_updateLogs(tableId, logs) {
        var $table = $('#' + tableId);
        $table.empty();
        if (!logs || logs.length === 0) {
            $table.append('<tr><td colspan="4">No log data available yet.</td></tr>');
            return;
        }
        logs.forEach(function(log) {
            var $row = $('<tr>');
            $row.append($('<td>').text(new Date(log.datetime * 1000).toLocaleString()));
            $row.append($('<td>').text(uptimerobot_getLogType(log.type)));
            $row.append($('<td>').text(log.duration + ' seconds'));
            $row.append($('<td>').text(log.reason.code + ' - ' + log.reason.detail));
            $table.append($row);
        });
    }

    function uptimerobot_getLogType(type) {
        switch(type) {
            case 1: return 'Down';
            case 2: return 'Up';
            case 98: return 'Started';
            case 99: return 'Paused';
            default: return 'Unknown';
        }
    }

    function uptimerobot_setupMonitors() {
        $.ajax({
            url: wpspeedtestpro_uptime.ajax_url,
            type: 'POST',
            data: {
                action: 'wpspeedtestpro_uptimerobot_setup_monitors',
                nonce: wpspeedtestpro_uptime.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Monitors set up successfully. Reloading page...');
                    location.reload();
                } else {
                    let errorMessage = 'Error setting up monitors: ' + (response.data || 'Unknown error');
                    if (response.data && response.data.includes('already exists')) {
                        errorMessage += '\n\nPlease go to your UptimeRobot account and manually delete any existing monitors for this site before trying again.';
                    }
                    alert(errorMessage);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('Error setting up monitors: ' + textStatus + ' - ' + errorThrown);
            }
        });
    }
    

    function uptimerobot_deleteMonitors() {
        if (confirm('Are you sure you want to delete the monitors?')) {
            $.ajax({
                url: wpspeedtestpro_uptime.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpspeedtestpro_uptimerobot_delete_monitors',
                    nonce: wpspeedtestpro_uptime.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Monitors deleted successfully. Reloading page...');
                        location.reload();
                    } else {
                        alert('Error deleting monitors: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error deleting monitors. Please try again.');
                }
            });
        }
    }

    function uptimerobot_recreateMonitors() {
        if (confirm('Are you sure you want to recreate the monitors?')) {
            $.ajax({
                url: wpspeedtestpro_uptime.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpspeedtestpro_uptimerobot_recreate_monitors',
                    nonce: wpspeedtestpro_uptime.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Monitors recreated successfully. Reloading page...');
                        location.reload();
                    } else {
                        alert('Error recreating monitors: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error recreating monitors. Please try again.');
                }
            });
        }
    }
})(jQuery);