/**
 * Hey Trisha - Terms and Conditions Activation Modal
 * Uses WordPress Thickbox modal system
 */

(function($) {
    'use strict';
    
    // Wait for DOM and jQuery
    $(document).ready(function() {
        
        // Check if we're on plugins page
        var isPluginsPage = (typeof pagenow !== 'undefined' && pagenow === 'plugins') ||
                            (window.location.href && window.location.href.indexOf('plugins.php') !== -1);
        
        if (!isPluginsPage) {
            return;
        }
        
        console.log('Hey Trisha: Activation terms script loaded');
        
        // Plugin identifiers - handle versioned directories
        var pluginSlug = typeof heytrishaTermsConfig !== 'undefined' && heytrishaTermsConfig.pluginSlug 
            ? heytrishaTermsConfig.pluginSlug 
            : 'heytrisha-woo/heytrisha-woo.php';
        
        // Function to find and intercept activation links
        function interceptActivation() {
            // Find activate links - multiple selectors for reliability
            var selectors = [
                'a[href*="action=activate"][href*="heytrisha-woo"]',
                'a[href*="action=activate"][href*="heytrisha"]',
                'tr[data-plugin*="heytrisha"] .activate a',
                'tr[data-plugin*="heytrisha-woo"] .activate a'
            ];
            
            var activateLinks = $();
            selectors.forEach(function(selector) {
                activateLinks = activateLinks.add($(selector));
            });
            
            // Fallback: find by plugin name
            if (activateLinks.length === 0) {
                $('tr').each(function() {
                    var $row = $(this);
                    var pluginName = $row.find('.plugin-title strong').text() || '';
                    var pluginDesc = $row.find('.plugin-description').text() || '';
                    
                    if (pluginName.indexOf('Hey Trisha') !== -1 || 
                        pluginName.indexOf('heytrisha') !== -1 ||
                        pluginDesc.indexOf('Hey Trisha') !== -1 ||
                        pluginDesc.indexOf('AI-Powered WordPress') !== -1) {
                        var $link = $row.find('.activate a');
                        if ($link.length > 0) {
                            activateLinks = activateLinks.add($link);
                        }
                    }
                });
            }
            
            console.log('Hey Trisha: Found ' + activateLinks.length + ' activate links');
            
            // Intercept each activate link
            activateLinks.each(function() {
                var $link = $(this);
                
                // Skip if already processed
                if ($link.data('heytrisha-intercepted')) {
                    return;
                }
                
                $link.data('heytrisha-intercepted', true);
                
                // Store original href
                var originalHref = $link.attr('href');
                
                console.log('Hey Trisha: Intercepting activation link:', originalHref);
                
                // Remove existing handlers and add our own
                $link.off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    console.log('Hey Trisha: Activation clicked - showing Terms modal');
                    
                    // Show WordPress Thickbox modal with Terms and Conditions
                    showTermsThickbox(originalHref);
                    
                    return false;
                });
            });
        }
        
        // Show Terms and Conditions using WordPress Thickbox
        function showTermsThickbox(activateUrl) {
            // Check if config is available
            if (typeof heytrishaTermsConfig === 'undefined') {
                console.error('Hey Trisha: Config not found!');
                alert('Error: Plugin configuration not loaded. Please refresh the page.');
                return;
            }
            
            // Create modal content HTML
            var modalContent = '<div id="heytrisha-terms-content" style="padding: 20px; max-width: 700px;">' +
                '<h2 style="margin-top: 0;">Terms and Conditions</h2>' +
                
                '<div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">' +
                '<strong style="color: #856404; display: block; margin-bottom: 10px;">⚠️ Important Security Notice:</strong>' +
                '<p style="color: #856404; margin: 8px 0; line-height: 1.6;">' +
                'This plugin requires database access to function properly. For your security and data protection, ' +
                'please use <strong>read-only database user credentials</strong> when configuring this plugin. ' +
                'This ensures that the plugin can only read data and cannot modify or delete any information from your database.' +
                '</p>' +
                '<p style="color: #856404; margin: 8px 0; line-height: 1.6;">' +
                'Using read-only credentials provides an additional layer of security and prevents any accidental data modifications.' +
                '</p>' +
                '</div>' +
                
                '<div style="margin: 20px 0;">' +
                '<p>By activating this plugin, you agree to the following:</p>' +
                '<ul style="margin: 15px 0; padding-left: 25px; line-height: 1.8;">' +
                '<li>You understand that this plugin requires database access to provide analytical insights</li>' +
                '<li>You will use read-only database credentials for security purposes</li>' +
                '<li>You acknowledge that the plugin accesses your WordPress and WooCommerce data</li>' +
                '<li>You agree to the <a href="https://heytrisha.com/terms-and-conditions" target="_blank">Terms and Conditions</a> of Hey Trisha</li>' +
                '<li>You understand that this plugin is designed for data analytics only, not data extraction</li>' +
                '</ul>' +
                '<p><a href="https://heytrisha.com/terms-and-conditions" target="_blank">Read full Terms and Conditions →</a></p>' +
                '</div>' +
                
                '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">' +
                '<label style="display: flex; align-items: flex-start; cursor: pointer;">' +
                '<input type="checkbox" id="heytrisha-accept-terms-checkbox" style="margin: 3px 10px 0 0; width: 18px; height: 18px; cursor: pointer;" />' +
                '<span style="flex: 1; line-height: 1.5;">I have read and agree to the Terms and Conditions</span>' +
                '</label>' +
                '</div>' +
                
                '<div style="text-align: right; margin-top: 25px; padding-top: 20px; border-top: 1px solid #ddd;">' +
                '<button type="button" id="heytrisha-terms-cancel" class="button" style="margin-right: 10px;">Cancel</button>' +
                '<button type="button" id="heytrisha-terms-activate" class="button button-primary" disabled>Activate Plugin</button>' +
                '</div>' +
                '</div>';
            
            // Create hidden div with content for Thickbox
            var $modalDiv = $('#heytrisha-terms-modal-div');
            if ($modalDiv.length === 0) {
                $modalDiv = $('<div id="heytrisha-terms-modal-div" style="display: none;">' + modalContent + '</div>');
                $('body').append($modalDiv);
            } else {
                $modalDiv.html(modalContent);
            }
            
            // Show WordPress Thickbox modal
            // Check if Thickbox is available
            if (typeof tb_show === 'function') {
                // Thickbox is loaded, use it directly
                tb_show('Terms and Conditions', '#TB_inline?inlineId=heytrisha-terms-modal-div&width=750&height=600');
            } else {
                // Thickbox not loaded - try to load it
                console.warn('Hey Trisha: Thickbox not found, loading dynamically...');
                var includesUrl = typeof heytrishaTermsConfig !== 'undefined' && heytrishaTermsConfig.includesUrl
                    ? heytrishaTermsConfig.includesUrl
                    : '/wp-includes/';
                
                // Load Thickbox script and CSS
                $.when(
                    $.getScript(includesUrl + 'js/thickbox/thickbox.js'),
                    $('<link>').attr({
                        rel: 'stylesheet',
                        href: includesUrl + 'js/thickbox/thickbox.css'
                    }).appendTo('head')
                ).done(function() {
                    console.log('Hey Trisha: Thickbox loaded successfully');
                    // Wait a moment for Thickbox to initialize
                    setTimeout(function() {
                        if (typeof tb_show === 'function') {
                            tb_show('Terms and Conditions', '#TB_inline?inlineId=heytrisha-terms-modal-div&width=750&height=600');
                        } else {
                            console.error('Hey Trisha: Thickbox still not available');
                            showFallbackConfirm(activateUrl);
                        }
                    }, 200);
                }).fail(function() {
                    console.error('Hey Trisha: Failed to load Thickbox');
                    showFallbackConfirm(activateUrl);
                });
            }
            
            // Fallback confirm dialog if Thickbox fails
            function showFallbackConfirm(activateUrl) {
                var accept = confirm('TERMS AND CONDITIONS\n\n' +
                    '⚠️ IMPORTANT SECURITY NOTICE:\n' +
                    'This plugin requires database access. Please use READ-ONLY database credentials.\n\n' +
                    'By activating, you agree to:\n' +
                    '1. Use read-only database credentials\n' +
                    '2. Accept Terms: https://heytrisha.com/terms-and-conditions\n' +
                    '3. Understand this is for analytics only\n\n' +
                    'Do you accept the Terms and Conditions?');
                
                if (accept) {
                    $.ajax({
                        url: heytrishaTermsConfig.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'heytrisha_accept_terms',
                            nonce: heytrishaTermsConfig.nonce,
                            accepted: 'true'
                        },
                        success: function(response) {
                            if (response.success) {
                                localStorage.setItem('heytrisha_terms_accepted', 'true');
                                window.location.href = activateUrl;
                            } else {
                                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to save acceptance.'));
                            }
                        },
                        error: function() {
                            alert('Error: Failed to communicate with server.');
                        }
                    });
                }
            }
            
            // Handle checkbox change
            $(document).off('change', '#heytrisha-accept-terms-checkbox').on('change', '#heytrisha-accept-terms-checkbox', function() {
                $('#heytrisha-terms-activate').prop('disabled', !this.checked);
            });
            
            // Handle activate button
            $(document).off('click', '#heytrisha-terms-activate').on('click', '#heytrisha-terms-activate', function() {
                if (!$('#heytrisha-accept-terms-checkbox').is(':checked')) {
                    alert('Please check the box to accept the Terms and Conditions.');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Processing...');
                
                // Save acceptance via AJAX
                $.ajax({
                    url: heytrishaTermsConfig.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'heytrisha_accept_terms',
                        nonce: heytrishaTermsConfig.nonce,
                        accepted: 'true'
                    },
                    success: function(response) {
                        if (response.success) {
                            localStorage.setItem('heytrisha_terms_accepted', 'true');
                            console.log('Hey Trisha: Terms accepted, activating plugin');
                            
                            // Close Thickbox
                            if (typeof tb_remove === 'function') {
                                tb_remove();
                            }
                            
                            // Proceed with activation
                            setTimeout(function() {
                                window.location.href = activateUrl;
                            }, 100);
                        } else {
                            $btn.prop('disabled', false).text('Activate Plugin');
                            alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to save acceptance.'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Activate Plugin');
                        alert('Error: Failed to communicate with server. Please try again.');
                    }
                });
            });
            
            // Handle cancel button
            $(document).off('click', '#heytrisha-terms-cancel').on('click', '#heytrisha-terms-cancel', function() {
                if (typeof tb_remove === 'function') {
                    tb_remove();
                } else {
                    $('#heytrisha-terms-modal-div').remove();
                }
            });
        }
        
        // Initial intercept
        interceptActivation();
        
        // Re-intercept on DOM changes (WordPress may reload plugin list)
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
                interceptActivation();
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        // Also check periodically
        setInterval(interceptActivation, 1000);
    });
    
})(jQuery);
