document.addEventListener('DOMContentLoaded', function () {
  const navItems = document.querySelectorAll('.myaccount_navigation_s33 li[data-section]');
  const sections = document.querySelectorAll('.account-section');

  // Tabs switching
  navItems.forEach(item => {
    item.addEventListener('click', function () {
      navItems.forEach(i => i.classList.remove('active'));
      this.classList.add('active');
      sections.forEach(s => s.style.display = 'none');
      const targetSection = document.getElementById(this.dataset.section + '-section');
      if (targetSection) {
        targetSection.style.display = 'block';
      }
      history.pushState({}, '', `/my-account/#${this.dataset.section}`);

      // Lazy load orders if clicking on orders tab
      if (this.dataset.section === 'orders') {
        loadOrders(1);
      }
    });
  });

  // Set initial section based on URL hash (e.g., #orders)
  const hash = location.hash.replace('#', '');
  if (hash) {
    const initialItem = document.querySelector(`[data-section="${hash}"]`);
    if (initialItem) {
      initialItem.click();
    }
  }

  // Inline editing for billing fields in dashboard
  const editBtn = document.querySelector('.account_page_s32_col_1_line_4_edit');
  const saveBtn = document.querySelector('.account_page_s32_col_1_line_4_save');
  const fields = document.querySelectorAll('[data-field]');

  if (editBtn) {
    editBtn.addEventListener('click', function () {
      fields.forEach(field => {
        const value = field.textContent.trim();
        const input = document.createElement('input');
        input.value = value;
        input.name = field.dataset.field;
        field.innerHTML = '';
        field.appendChild(input);
        field.classList.add('editing');
      });
      editBtn.style.display = 'none';
      saveBtn.style.display = 'block';
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      const data = new FormData();
      fields.forEach(field => {
        const input = field.querySelector('input');
        if (input) {
          data.append(input.name, input.value);
        }
      });
      data.append('action', 'save_address'); // Use address save handler for billing
      data.append('nonce', ajax_object.nonce); // Add nonce if needed (adjust based on handler)

      fetch(ajax_object.ajax_url, {
        method: 'POST',
        body: data
      }).then(response => response.json())
        .then(result => {
          if (result.success) {
            location.reload(); // Reload to update display
          } else {
            alert('Ошибка сохранения');
          }
        }).catch(err => console.error('Error:', err));
    });
  }

  // Lazy load orders function
  function loadOrders(paged) {
    const list = document.querySelector('.orders-list');
    const pag = document.querySelector('.orders-pagination');
    if (!list || !pag) return;

    list.innerHTML = '<li>Загрузка...</li>'; // Loading placeholder
    fetch(ajax_object.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=load_orders&paged=${paged}&nonce=${ajax_object.nonce}`
    }).then(response => response.json())
      .then(result => {
        if (result.success) {
          list.innerHTML = result.data.html;
          pag.innerHTML = '';
          for (let i = 1; i <= result.data.total_pages; i++) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = i;
            if (i === paged) link.classList.add('active');
            link.addEventListener('click', e => {
              e.preventDefault();
              loadOrders(i);
            });
            pag.appendChild(link);
          }
        } else {
          list.innerHTML = '<li>Ошибка загрузки заказов.</li>';
        }
      }).catch(err => {
        list.innerHTML = '<li>Ошибка: ' + err.message + '</li>';
      });
  }

  // AJAX submit for address form
  const addressForm = document.getElementById('edit-address-form');
  if (addressForm) {
    addressForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        this.reportValidity();
        return;
      }
      const data = new FormData(this);
      data.append('action', 'save_address');
      fetch(ajax_object.ajax_url, {
        method: 'POST',
        body: data
      }).then(res => res.json())
        .then(result => {
          if (result.success) {
            alert(result.data.message);
            location.reload();
          } else {
            alert(result.data.message);
          }
        }).catch(err => alert('Ошибка: ' + err));
    });
  }

  // AJAX submit for account form with password match check
  const accountForm = document.getElementById('edit-account-form');
  if (accountForm) {
    accountForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const pass1 = this.querySelector('#password_1').value;
      const pass2 = this.querySelector('#password_2').value;
      if (pass1 && pass1 !== pass2) {
        alert('Пароли не совпадают!');
        return;
      }
      if (!this.checkValidity()) {
        this.reportValidity();
        return;
      }
      const data = new FormData(this);
      data.append('action', 'save_account');
      fetch(ajax_object.ajax_url, {
        method: 'POST',
        body: data
      }).then(res => res.json())
        .then(result => {
          if (result.success) {
            alert(result.data.message);
            location.reload();
          } else {
            alert(result.data.message);
          }
        }).catch(err => alert('Ошибка: ' + err));
    });
  }

});
