// version 2.7.6

console.log('555');

// custom-dashboard.js

function initAccountDashboard() {
  console.log('initAccountDashboard: Starting initialization');
  // Check if we are on the account page with our custom elements
  const nav = document.querySelector('.myaccount_navigation_s33');
  if (!nav) {
    console.log('initAccountDashboard: No navigation found, exiting');
    return; // Exit if not on custom account page
  }
  console.log('initAccountDashboard: Navigation found, proceeding');

  const navItems = document.querySelectorAll('.myaccount_navigation_s33 li[data-section]');
  const sections = document.querySelectorAll('.account-section');
  console.log('initAccountDashboard: Found', navItems.length, 'nav items and', sections.length, 'sections');

  // Tabs switching function
  function switchTab(section) {
    console.log('switchTab: Switching to section', section);
    navItems.forEach(i => i.classList.remove('active'));
    const activeItem = document.querySelector(`[data-section="${section}"]`);
    if (activeItem) {
      activeItem.classList.add('active');
      console.log('switchTab: Activated item for', section);
    } else {
      console.log('switchTab: No active item found for', section);
    }
    sections.forEach(s => s.style.display = 'none');
    const targetSection = document.getElementById(section + '-section');
    if (targetSection) {
      targetSection.style.display = 'block';
      console.log('switchTab: Displayed section', section);
    } else {
      console.log('switchTab: No target section found for', section);
    }

    // Lazy load orders if switching to orders
    if (section === 'orders') {
      console.log('switchTab: Loading orders for section orders');
      loadOrders(1);
    }
  }

  // Bind click events
  navItems.forEach(item => {
    item.addEventListener('click', function () {
      const section = this.dataset.section;
      console.log('Nav click: Clicked on', section);
      switchTab(section);
      history.pushState({ section }, '', `/my-account/#${section}`);
      console.log('Nav click: Pushed state for', section);
    });
  });
  console.log('initAccountDashboard: Bound click events to nav items');

  // Initial tab based on hash
  const hash = location.hash.replace('#', '');
  if (hash) {
    console.log('initAccountDashboard: Initial hash found', hash);
    switchTab(hash);
  } else {
    console.log('initAccountDashboard: No initial hash');
  }

  // Forbidden fields for readonly
  const forbidden = ['account_first_name', 'account_email', 'billing_phone', 'billing_email'];
  console.log('initAccountDashboard: Forbidden fields set');

  // Inline editing for address in dashboard
  const addressEditBtn = document.querySelector('.address-edit-btn');
  const addressSaveBtn = document.querySelector('.address-save-btn');
  const addressFields = document.querySelectorAll('#dashboard-section [data-field]');

  if (addressEditBtn) {
    console.log('initAccountDashboard: Address edit button found');
    addressEditBtn.addEventListener('click', function () {
      console.log('Address edit: Starting edit');
      addressFields.forEach(field => {
        const value = field.textContent.trim();
        let inputElement;
        if (field.dataset.field === 'billing_country') {
          inputElement = document.createElement('select');
          inputElement.name = field.dataset.field;
          for (let code in ajax_object.countries) {
            const option = document.createElement('option');
            option.value = code;
            option.text = ajax_object.countries[code];
            if (code === value) option.selected = true;
            inputElement.add(option);
          }
          console.log('Address edit: Created select for country');
        } else {
          inputElement = document.createElement('input');
          inputElement.value = value;
          inputElement.name = field.dataset.field;
          console.log('Address edit: Created input for', field.dataset.field);
        }
        if (forbidden.includes(field.dataset.field)) {
          inputElement.readOnly = true;
          console.log('Address edit: Set readonly for', field.dataset.field);
        }
        field.innerHTML = '';
        field.appendChild(inputElement);
        field.classList.add('editing');
      });
      addressEditBtn.style.display = 'none';
      addressSaveBtn.style.display = 'block';
      console.log('Address edit: Edit mode activated');
    });
  } else {
    console.log('initAccountDashboard: No address edit button found');
  }

  // Lazy load orders function
  function loadOrders(paged) {
    console.log('loadOrders: Loading page', paged);
    const list = document.querySelector('.orders-list');
    const pag = document.querySelector('.orders-pagination');
    if (!list || !pag) {
      console.log('loadOrders: No list or pagination found');
      return;
    }

    list.innerHTML = '<p>Загрузка...</p>';
    console.log('loadOrders: Set loading message');

    fetch(ajax_object.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=load_orders&paged=${paged}&nonce=${ajax_object.nonce}`
    }).then(response => {
      console.log('loadOrders: Fetch response received');
      return response.json();
    })
      .then(result => {
        console.log('loadOrders: Result', result);
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
              console.log('Pagination click: Loading page', i);
              loadOrders(i);
            });
            pag.appendChild(link);
          }
          console.log('loadOrders: Updated list and pagination');
        } else {
          list.innerHTML = '<p>Ошибка загрузки заказов.</p>';
          console.log('loadOrders: Error loading orders');
        }
      }).catch(err => {
        list.innerHTML = '<p>Ошибка: ' + err.message + '</p>';
        console.error('loadOrders: Error:', err);
      });
  }

  console.log('initAccountDashboard: Initialization complete');
}

// Initialize on page load and when cached page is shown (for back button)
window.addEventListener('pageshow', function (event) {
  console.log('pageshow: Event fired', event.persisted ? 'from bfcache' : 'normal load');
  if (event.persisted) {
    console.log('pageshow: From bfcache, forcing reload');
    window.location.reload();
  } else {
    initAccountDashboard();
  }
});

// Handle history navigation (popstate for back/forward)
window.addEventListener('popstate', function (event) {
  console.log('popstate: Event fired', event.state);
  const hash = location.hash.replace('#', '');
  if (hash && event.state && event.state.section === hash) {
    console.log('popstate: Matching state, clicking item', hash);
    const activeItem = document.querySelector(`[data-section="${hash}"]`);
    if (activeItem) {
      activeItem.click();
    }
  } else {
    console.log('popstate: No matching state, re-initializing');
    initAccountDashboard(); // Re-init if needed
  }
});
