<!-- sign-in form v.2.1 -->
<div id="signin-form" class="signin_form account_form_item active">
  
  <div class="signin_form_wrap">
    <form id="signin2" class="signin_form_box" name="loginform" method="post">
      <label for="login">Имя или Email:</label>
      <input type="text" id="login" name="login" required>
      <label for="password-login">Пароль:</label>
      <input type="password" id="password-login" name="password" required>
      <input type="hidden" name="auth_nonce" value="<?php echo wp_create_nonce('custom_auth_action'); ?>">
      <button type="submit" name="signin_submit">Войти</button>
      <label for="rememberme" class="signin_form_rememberme_checkbox">
        <input type="checkbox" id="rememberme" name="rememberme"> 
        <span>Запомнить меня</span>
      </label>
    </form>
    <button class="signin_form_forgot_btn">Восстановить пароль</button>
    <div class="error-message"></div>
  </div>

  <!-- sign-in flash call form -->
  <div class="signin_form_flash_call_wrap">
    <div class="signin_form_flash_call">
      <input type="tel" id="phone_number" name="phone_number" placeholder="+7 (___) ___-__-__" required>
      <button type="button" id="send_code">Войти</button>
      <div id="verification_section" style="display: none;">
        <input type="text" id="verification_code" name="verification_code" placeholder="xxxx" required>
        <button type="button" id="verify_code">Войти</button>
      </div>
      <p id="message"></p>
    </div>
    <div class="signin_form_arragement">
      <input type="checkbox" checked disabled>
      <span>Нажимая кнопку входа вы даете согласие на то, что ваши личные данные будут использоваться для обработки ваших заказов, упрощения работы с сайтом и для других целей, описанных в нашем <a href="/polzovatelskoe-soglashenie/">пользовательском соглашении</a></span>
    </div>
  </div>

  <!-- Вход через Яндекс ID -->
  <a href="/yandex-id/" class="signin_form_yandex_id"> 
    <button>
      <span>Вход через Яндекс ID</span>
      <img src="<?php echo get_template_directory_uri();?>/assets/img/logo/yandex-id.jpg" alt="yandex">
    </button>
  </a>

</div>