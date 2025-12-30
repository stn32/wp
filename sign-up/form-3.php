<!-- sign-up form v.2.1 -->
<div id="signup-form" class="signup_form account_form_item">
    <form id="signup2" class="signup_form_box" name="signupform" method="post">
        <label for="username-signup">Логин:</label>
        <input type="text" id="username-signup" name="username" required>

        <label for="email-signup">Email:</label>
        <input type="email" id="email-signup" name="email" required>

        <label for="bill-phone-signup">Телефон:</label>
        <input type="phone" id="bill-phone-signup" name="phone" required>

        <label for="password-signup">Пароль:</label>
        <input type="password" id="password-signup" name="password" required>

        <input type="hidden" name="auth_nonce" value="<?php echo wp_create_nonce('custom_auth_action'); ?>">

        <button type="submit" name="signup_submit">Регистрация</button>
    </form>
    <a href="/yandex-id/" class="signup_form_yandex_id"> 
        <button>
            <span>Регистрация через Яндекс ID</span>
            <img src="<?php echo get_template_directory_uri();?>/assets/img/logo/yandex-id.jpg" alt="yandex">
        </button>
    </a>
    <div class="signup_form_arragement">
        <input type="checkbox" checked disabled>
        <span>Нажимая кнопку "Регистрация" вы даете согласие на то, что ваши личные данные будут использоваться для обработки ваших заказов, упрощения работы с сайтом и для других целей, описанных в нашем <a href="/polzovatelskoe-soglashenie/">пользовательском соглашении</a></span>
    </div>
    <div class="error-message"></div>
</div>
<!-- sign-up form v.2.1 ajax -->
<script>
    document.getElementById('signup2').addEventListener('submit', async function (e) {
        e.preventDefault(); // Prevent form from reloading the page

        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', 'custom_register'); // Add the action parameter for the sign-up logic

        try {
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();
            const errorMessageContainer = document.querySelector('#signup-form .error-message');

            if (result.success) {
                // Reload the page on success
                location.reload();
            } else {
                // Display error message with HTML content
                errorMessageContainer.innerHTML = result.data.message; // Use innerHTML to allow HTML tags
            }
        } catch (error) {
            document.querySelector('#signup-form .error-message').innerText = 'Ошибка: неверные данные';
        }
    });
</script>