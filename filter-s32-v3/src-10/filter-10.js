/**
 * product loop
 * filter size assist
 * hide out of stock products
 */

document.addEventListener('DOMContentLoaded', () => {

  /**
   * hide backorder box when scrolling
   */
  function hideBackorderWhenScrolling() {
    const filterBackorderBox = document.querySelector('.filters_2024_backorder');
    if (filterBackorderBox) {
      const isDesktop = () => window.innerWidth >= 1024; // Define desktop breakpoint
      const handleScroll = () => {
        if (isDesktop()) {
          if (window.scrollY === 0) {
            filterBackorderBox.style.display = 'block'; // Show box at the top
          } else {
            filterBackorderBox.style.display = 'none'; // Hide box when scrolling
          }
        } else {
          filterBackorderBox.style.display = ''; // Reset display for non-desktop devices
        }
      };
      window.addEventListener('scroll', handleScroll);
      handleScroll(); // Initial check on page load
    }
  }

  /**
   * apply the filter with an interval
   */
  let filters2024Apply2 = document.querySelector('.filters_2024_apply');
  if (filters2024Apply2) {
    setInterval(() => {
      // filterSizeAssist();
      hideBackorderWhenScrolling(); // hide backorder box when scrolling
    }, 1500);
  }




  // (function () {

  //   function hasProducts(root) {
  //     return !!(
  //       root.querySelector("ul.products li.product") ||
  //       root.querySelector(".products li.product") ||
  //       root.querySelector(".product.type-product") ||
  //       root.querySelector("li.product")
  //     );
  //   }

  //   function getContainers() {
  //     return {
  //       wc: document.querySelector(".woocommerce.columns-4, .woocommerce"),
  //       breadcrumb: document.querySelector(".woocommerce-breadcrumb"),
  //       main: document.querySelector("main#primary.site-main")
  //     };
  //   }

  //   function showNoProductsState() {
  //     var { wc, breadcrumb, main } = getContainers();
  //     if (!wc) return;

  //     if (wc.querySelector(".filter-s32-no-products")) return;

  //     if (hasProducts(wc)) return;

  //     // скрываем хлебные крошки и основной блок
  //     if (breadcrumb) breadcrumb.style.display = "none";
  //     if (main) main.style.display = "none";

  //     // создаём сообщение
  //     var box = document.createElement("div");
  //     box.className = "woocommerce-info filter-s32-no-products";
  //     box.style.margin = "120px auto";
  //     box.style.maxWidth = "600px";
  //     box.style.minHeight = "300px";
  //     box.innerHTML =
  //       '<p style="margin:0 0 16px; text-align:center;">Товаров с данными параметрами нет</p>' +
  //       '<p style="margin:0; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">' +
  //       '  <a class="button filter-s32-back" href="#">Назад</a>' +
  //       '  <a class="button filter-s32-reset" href="' + location.pathname + '">Сбросить фильтры</a>' +
  //       "</p>";

  //     // вставляем сообщение перед main (чтобы не было внутри скрытого блока)
  //     if (main && main.parentNode) {
  //       main.parentNode.insertBefore(box, main);
  //     } else {
  //       document.body.appendChild(box);
  //     }

  //     box.querySelector(".filter-s32-back").addEventListener("click", function (e) {
  //       e.preventDefault();
  //       if (window.history.length > 1) window.history.back();
  //       else window.location.href = "/shop/";
  //     });
  //   }

  //   function restoreIfProductsAppear() {
  //     var { wc, breadcrumb, main } = getContainers();
  //     if (!wc) return;

  //     if (hasProducts(wc)) {
  //       var msg = document.querySelector(".filter-s32-no-products");
  //       if (msg) msg.remove();

  //       if (breadcrumb) breadcrumb.style.display = "";
  //       if (main) main.style.display = "";
  //     }
  //   }

  //   function check() {
  //     showNoProductsState();
  //     restoreIfProductsAppear();
  //   }

  //   document.addEventListener("DOMContentLoaded", function () {
  //     check();
  //     setTimeout(check, 300);
  //     setTimeout(check, 1000);
  //   });

  //   window.addEventListener("load", check);

  // })();





})



// /**
//  * filter size assist
//  */
// function filterSizeAssist() {
//   let filterInitMain = document.querySelector('.filters_2024');
//   if (filterInitMain) {
//     const sizeFilters = [
//       { input: '.filters_2024 .filter-option-size label input[value="34"]', class: 'variation-size-34' },
//       { input: '.filters_2024 .filter-option-size label input[value="35"]', class: 'variation-size-35' },
//       { input: '.filters_2024 .filter-option-size label input[value="36"]', class: 'variation-size-36' },
//       { input: '.filters_2024 .filter-option-size label input[value="37"]', class: 'variation-size-37' },
//       { input: '.filters_2024 .filter-option-size label input[value="38"]', class: 'variation-size-38' },
//       { input: '.filters_2024 .filter-option-size label input[value="39"]', class: 'variation-size-39' },
//       { input: '.filters_2024 .filter-option-size label input[value="40"]', class: 'variation-size-40' },
//       { input: '.filters_2024 .filter-option-size label input[value="xs"]', class: 'variation-size-XS' },
//       { input: '.filters_2024 .filter-option-size label input[value="s"]', class: 'variation-size-S' },
//       { input: '.filters_2024 .filter-option-size label input[value="m"]', class: 'variation-size-M' },
//       { input: '.filters_2024 .filter-option-size label input[value="l"]', class: 'variation-size-L' },
//       { input: '.filters_2024 .filter-option-size label input[value="xl"]', class: 'variation-size-XL' }
//     ];

//     // Collect selected sizes
//     let selectedSizes = sizeFilters.filter(size => document.querySelector(size.input)?.checked);

//     // Only proceed if there are selected sizes; otherwise, leave products unchanged
//     if (selectedSizes.length > 0) {
//       let allWcLoopProduct = document.querySelectorAll('.woocommerce .products .product');
//       allWcLoopProduct.forEach(product => {
//         let isInStock = false; // Assume out of stock unless an in-stock size is found

//         // Check each selected size for this product
//         for (let size of selectedSizes) {
//           let variationBoxes = product.querySelectorAll(`.product_size_attributes .${size.class}`);

//           for (let box of variationBoxes) {

//             // xxx
//             if (box.classList.contains('stock-status-in-stock') || box.classList.contains('stock-status-notify')) {
//               isInStock = true;
//               break; // No need to check further for this product
//             }
//           }

//           if (isInStock) break; // If in stock for any size, skip checking other sizes
//         }

//         // Update display only if none of the selected sizes are in stock
//         product.style.display = isInStock ? '' : 'none';
//       });
//     }
//   }
// }