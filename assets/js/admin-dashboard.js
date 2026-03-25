jQuery(document).ready(function($) {
    var D = raplsaichDashboard || {};
    var ctx = document.getElementById('raplsaich-usage-chart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: D.labels || [],
                datasets: [
                    {
                        label: D.inputLabel || 'Input Tokens',
                        data: D.inputData || [],
                        backgroundColor: 'rgba(0, 163, 42, 0.6)',
                        borderColor: 'rgba(0, 163, 42, 1)',
                        borderWidth: 1
                    },
                    {
                        label: D.outputLabel || 'Output Tokens',
                        data: D.outputData || [],
                        backgroundColor: 'rgba(219, 166, 23, 0.6)',
                        borderColor: 'rgba(219, 166, 23, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                                return value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' ' + (D.tokensLabel || 'tokens');
                            }
                        }
                    }
                }
            }
        });
    }

    $('#raplsaich-reset-usage').on('click', function() {
        var $btn = $(this);
        if (!confirm(D.confirmReset || 'Reset?')) return;
        $btn.prop('disabled', true).text(D.resetting || 'Resetting...');
        raplsaichDestructiveAjax({
            data: { action: 'raplsaich_reset_usage', nonce: raplsaichAdmin.nonce },
            success: function(r) {
                if (r.success) { location.reload(); }
                else { alert(r.data || D.failedReset || 'Failed'); $btn.prop('disabled', false).html('🔄 ' + (D.resetLabel || 'Reset Statistics')); }
            },
            fail: function() { alert(D.errorOccurred || 'Error'); $btn.prop('disabled', false).html('🔄 ' + (D.resetLabel || 'Reset Statistics')); },
            cancel: function() { $btn.prop('disabled', false).html('🔄 ' + (D.resetLabel || 'Reset Statistics')); }
        });
    });
});
