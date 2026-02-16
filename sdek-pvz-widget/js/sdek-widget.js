jQuery(function ($) {

  console.log('=== СДЭК v3 — по всей России ===');

  function addSDEKButton() {
    if ($('#sdek-pvz-button').length) return;
    $('.woocommerce-shipping-methods').after(`
            <p class="form-row" id="sdek-pvz-wrapper" style="display:none; margin:15px 0;">
                <button type="button" id="sdek-pvz-button" class="button alt">Выбрать пункт выдачи СДЭК</button>
                <span id="sdek-selected-pvz" style="margin-left:12px; font-weight:bold; color:#333;"></span>
            </p>
        `);
  }

  $(document).on('click', '#sdek-pvz-button', function () {
    $('#sdek-modal').css('display', 'flex');
    initCDEKWidget();
  });

  window.closeSDEKModal = function () {
    $('#sdek-modal').hide();
    $('#cdek-widget-container').empty();
  };

  let currentWidget = null;

  function initCDEKWidget() {
    if (currentWidget) return;

    // Берём город из поля оформления заказа
    let city = $('#shipping_city').val() || $('#billing_city').val() || '';

    // Если город не указан — показываем карту всей России
    const config = {
      root: 'cdek-widget-container',
      apiKey: sdekParams.yandexKey,
      servicePath: sdekParams.servicePath,
      defaultLocation: city || null,     // null = вся Россия
      from: city || null,
      lang: 'rus',
      showCitySelect: true,              // позволяет выбирать любой город

      onChoose: function (mode, tariff, address) {
        console.log('✅ Выбран ПВЗ v3 →', address);

        if (mode === 'office' && address) {
          $('input[name="sdek_pvz_code"]').val(address.code || '');
          $('input[name="sdek_pvz_address"]').val(address.address || '');
          $('input[name="sdek_pvz_name"]').val(address.name || '');
          $('input[name="sdek_pvz_phone"]').val(address.phone || '');

          $('#sdek-selected-pvz').html(`
                        Выбрано: <strong>${address.name || 'ПВЗ'}</strong><br>
                        ${address.address || ''}
                    `);

          closeSDEKModal();
          $(document.body).trigger('update_checkout');
        }
      }
    };

    currentWidget = new window.CDEKWidget(config);
  }

  // Перезагружаем виджет при смене города на checkout
  $(document).on('change', '#shipping_city, #billing_city', function () {
    if (currentWidget) {
      currentWidget = null; // сброс
    }
  });

  $(document.body).on('updated_checkout', function () {
    addSDEKButton();
    const selected = $('input[name^="shipping_method"]:checked').val() || '';
    $('#sdek-pvz-wrapper').toggle(selected.indexOf('sdek_pvz') !== -1);
  });

  $(document).ready(function () {
    $(document.body).trigger('updated_checkout');
  });
});