jQuery(document).ready(function ($) {
    'use strict';

    function openPopup() {
        $('#otc-popup-overlay').addClass('active');
        $('body').addClass('otc-popup-open');

        setTimeout(function () {
            $('#otc-tracking-code').trigger('focus');
        }, 100);
    }

    function closePopup(reset) {
        $('#otc-popup-overlay').removeClass('active');
        $('body').removeClass('otc-popup-open');

        if (reset) {
            $('#otc-result').html('');
            var form = $('#otc-tracking-form')[0];
            if (form) {
                form.reset();
            }
        }
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(value) {
        return escapeHtml(value);
    }

    // باز کردن پاپ‌آپ
    $(document).on('click', '#otc-open-popup, .otc-open-popup, .otc-glass-button', function (e) {
        e.preventDefault();
        openPopup();
    });

    // بستن با دکمه ضربدر
    $(document).on('click', '.otc-close-popup', function (e) {
        e.preventDefault();
        closePopup(true);
    });

    // بستن با کلیک روی پس‌زمینه
    $(document).on('click', '#otc-popup-overlay', function (e) {
        if (e.target === this) {
            closePopup(true);
        }
    });

    // بستن با ESC
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closePopup(false);
        }
    });

    // ارسال فرم رهگیری
    $(document).on('submit', '#otc-tracking-form', function (e) {
        e.preventDefault();

        var trackingCode = $('#otc-tracking-code').val().trim();
        var $result = $('#otc-result');
        var $button = $('.otc-search-btn');

        if (!trackingCode) {
            $result.html('<div class="otc-error">لطفاً کد رهگیری یا شماره سفارش را وارد کنید.</div>');
            return;
        }

        var originalButtonHtml = $button.html();

        $button
            .html('<span class="dashicons dashicons-update"></span> در حال جستجو...')
            .prop('disabled', true);

        $result.html('');

        $.ajax({
            url: otc_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'search_order',
                tracking_code: trackingCode,
                nonce: otc_ajax.nonce
            },
            success: function (response) {
                $button.html(originalButtonHtml).prop('disabled', false);

                if (response && response.success) {
                    displayOrderInfo(response.data);
                } else {
                    var message = response && response.data && response.data.message
                        ? response.data.message
                        : 'سفارشی با این کد پیدا نشد.';

                    $result.html('<div class="otc-error">❌ ' + escapeHtml(message) + '</div>');
                }
            },
            error: function () {
                $button.html(originalButtonHtml).prop('disabled', false);
                $result.html('<div class="otc-error">❌ خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.</div>');
            }
        });
    });

    function displayOrderInfo(data) {
        var itemsHtml = '';

        if (data.items && data.items.length > 0) {
            itemsHtml += '<div class="otc-items-list">';
            itemsHtml += '<h4>📦 محصولات سفارش</h4>';

            data.items.forEach(function (item) {
                itemsHtml += '<div class="otc-item">';
                itemsHtml += '<span>' + escapeHtml(item.name) + ' × ' + escapeHtml(item.quantity) + '</span>';

                // total از wc_price می‌آید و ممکن است HTML قیمت داشته باشد
                itemsHtml += '<span>' + (item.total || '') + '</span>';

                itemsHtml += '</div>';
            });

            itemsHtml += '</div>';
        }

        var trackingLinkHtml = '';

        if (data.tracking_link) {
            trackingLinkHtml =
                '<a class="otc-tracking-link" href="' + escapeAttr(data.tracking_link) + '" target="_blank" rel="noopener noreferrer">' +
                'مشاهده در سایت رهگیری' +
                '</a>';
        }

        var companyHtml = '';

        if (data.tracking_company && data.tracking_company !== 'نامشخص') {
            companyHtml = '<div class="otc-company">شرکت حمل: ' + escapeHtml(data.tracking_company) + '</div>';
        }

        var html = '';

        html += '<div class="otc-success">';

        html += '<div class="otc-order-header">';
        html += '<div class="otc-order-number">سفارش #' + escapeHtml(data.order_number) + '</div>';
        html += '<div class="otc-order-status">' + escapeHtml(data.status_label) + '</div>';
        html += '</div>';

        html += '<div class="otc-info-grid">';

        html += '<div class="otc-info-item">';
        html += '<div class="otc-info-label">تاریخ ثبت سفارش</div>';
        html += '<div class="otc-info-value">' + escapeHtml(data.order_date) + '</div>';
        html += '</div>';

        html += '<div class="otc-info-item">';
        html += '<div class="otc-info-label">مبلغ سفارش</div>';

        // total از wc_price می‌آید
        html += '<div class="otc-info-value">' + (data.total || '') + '</div>';

        html += '</div>';

        html += '</div>';

        html += '<div class="otc-tracking-code">';
        html += '<strong>کد رهگیری مرسوله</strong>';
        html += '<div class="otc-code-value">' + escapeHtml(data.tracking_code) + '</div>';
        html += companyHtml;
        html += trackingLinkHtml;
        html += '</div>';

        html += itemsHtml;

        html += '</div>';

        $('#otc-result').html(html);
    }
});
