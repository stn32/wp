document.addEventListener('DOMContentLoaded', function () {
  const btnLm = document.querySelector('#s32-load-more');
  if (!btnLm || typeof s32_loadmore === 'undefined') return;

  const wcPaginationLm = document.querySelector('.woocommerce-pagination');
  let pageLm = parseInt(s32_loadmore.current_page);
  const maxPage = parseInt(s32_loadmore.max_page);
  let isLoading = false;

  if (wcPaginationLm) wcPaginationLm.style.display = 'none';

  // --- Уведомление ---
  function showNotice(message, type) {
    // type: 'warn' или 'error'
    var existing = document.querySelector('.s32-notice');
    if (existing) existing.remove();

    var notice = document.createElement('div');
    notice.className = 's32-notice s32-notice--' + type;
    notice.textContent = message;

    var close = document.createElement('span');
    close.textContent = '✕';
    close.className = 's32-notice-close';
    close.addEventListener('click', function () {
      notice.remove();
    });
    notice.appendChild(close);

    document.body.appendChild(notice);

    // warn исчезает сам через 6 сек, error висит до закрытия
    if (type === 'warn') {
      setTimeout(function () {
        if (notice.parentNode) notice.remove();
      }, 6000);
    }
  }

  // --- Основной обработчик ---
  btnLm.addEventListener('click', function () {
    if (isLoading || pageLm >= maxPage) return;
    loadPage(pageLm + 1, 0);
  });

  function loadPage(targetPage, attempt) {
    var maxAttempts = 3;
    var slowTimeout = null;
    isLoading = true;
    btnLm.classList.add('spin');
    btnLm.textContent = '';
    btnLm.disabled = true;

    // Таймер «медленного соединения» — сработает если запрос висит 5+ сек
    slowTimeout = setTimeout(function () {
      showNotice('Медленное соединение — загрузка товаров занимает больше времени…', 'warn');
    }, 5000);

    var controller = new AbortController();
    // Жёсткий таймаут 15 сек — дальше ждать нет смысла
    var hardTimeout = setTimeout(function () {
      controller.abort();
    }, 15000);

    var formDataLm = new FormData();
    formDataLm.append('action', 's32_load_more');
    formDataLm.append('page', targetPage);
    formDataLm.append('query_vars', JSON.stringify(s32_loadmore.query_vars));

    fetch(s32_loadmore.ajaxurl, {
      method: 'POST',
      body: formDataLm,
      signal: controller.signal,
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.text();
      })
      .then(function (html) {
        clearTimeout(slowTimeout);
        clearTimeout(hardTimeout);

        // Убираем предупреждение о медленном соединении, если оно было
        var existing = document.querySelector('.s32-notice--warn');
        if (existing) existing.remove();

        var temp = document.createElement('div');
        temp.innerHTML = html.trim();
        var newProducts = temp.querySelectorAll('.product');
        var productLoop =
          document.querySelector('ul.products') ||
          document.querySelector('.products');

        if (newProducts.length && productLoop) {
          newProducts.forEach(function (p) {
            productLoop.appendChild(p);
          });
          pageLm = targetPage;

          // Уведомляем другие плагины, что товары вставлены
          document.dispatchEvent(new CustomEvent('s32:products_loaded'));
        } else {
          console.warn('[LoadMore] Пустой ответ для страницы ' + targetPage);
        }

        resetBtn();

        if (pageLm >= maxPage) {
          btnLm.style.display = 'none';
        }
      })
      .catch(function (err) {
        clearTimeout(slowTimeout);
        clearTimeout(hardTimeout);

        var reason = err.name === 'AbortError'
          ? 'Превышено время ожидания (15 сек)'
          : 'Ошибка сети: ' + err.message;

        console.error('[LoadMore] Попытка ' + (attempt + 1) + ':', reason);

        if (attempt + 1 < maxAttempts) {
          showNotice('Не удалось загрузить — повторная попытка…', 'warn');
          setTimeout(function () {
            loadPage(targetPage, attempt + 1);
          }, 1500 * (attempt + 1));
        } else {
          showNotice('Не удалось загрузить товары. Причина: ' + reason + '. Проверьте соединение и попробуйте ещё раз.', 'error');
          resetBtn();
        }
      });
  }

  function resetBtn() {
    isLoading = false;
    btnLm.classList.remove('spin');
    btnLm.disabled = false;
    if (btnLm.textContent === '') {
      btnLm.textContent = 'Показать ещё';
    }
  }
});