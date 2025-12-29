document.addEventListener("DOMContentLoaded", function () {

  const sendCodeBtn = document.getElementById("send_code");
  const verifyCodeBtn = document.getElementById("verify_code");
  const phoneNumberInput = document.getElementById("phone_number");

  const messageBox = document.getElementById("message");
  const verificationSection = document.getElementById("verification_section");

  if (!sendCodeBtn) {
    return;
  }

  // send Code
  sendCodeBtn.addEventListener("click", function () {

    // let phoneNumber = phoneNumberInput.value.trim();
    // phoneNumber = phoneNumber.replace(/\D/g, ''); // Remove all non-digit characters

    let phoneNumber = phoneNumberInput.value.trim().replace(/\D/g, '');
    if (phoneNumber[0] === '8') {
      phoneNumber = '7' + phoneNumber.slice(1);
    }

    // Check if phone number is empty, too short, or doesn't start with '7' or '8'
    if (!phoneNumber || phoneNumber.length < 11 || (phoneNumber[0] !== '7' && phoneNumber[0] !== '8')) {
      messageBox.textContent = "Введите номер";
      return;
    }
    phoneNumberInput.value = phoneNumber; // If phone number is valid, you can continue processing

    setTimeout(() => {
      fetch('/wp-json/custom-auth/v1/send-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: phoneNumber })
      })

        // .then(response => response.json())
        // .then(data => {
        //     console.log("API Response:", data); // Debugging log
        //     if (data.success) {
        //         messageBox.textContent = "Мы звоним вам, пожалуйста, введите 4 последних цифры";
        //         sendCodeBtn.style.display = "none";
        //         verificationSection.style.display = "flex";
        //     } else {
        //         messageBox.textContent = "Error: " + (data.error || 'Unknown error');
        //         setTimeout(() => {
        //             location.reload();
        //         }, 2000);
        //     }
        // })

        .then(response => response.json())
        .then(data => {
          console.log("API Response:", data); // Debugging log
          if (data.success) {
            messageBox.textContent = "Мы звоним вам, пожалуйста, введите 4 последних цифры";
            sendCodeBtn.style.display = "none";
            verificationSection.style.display = "flex";
          } else {
            messageBox.textContent = "Ошибка: " + (data.error || 'Неизвестная ошибка');

            // Soft UI Reset
            setTimeout(() => {
              // Reset phone input and button visibility
              phoneNumberInput.value = "";
              sendCodeBtn.style.display = "inline-block";
              sendCodeBtn.disabled = false;
              phoneNumberInput.disabled = false;

              verificationSection.style.display = "none";
              messageBox.textContent = "";

              // Optional: focus input again
              phoneNumberInput.focus();
            }, 2000);
          }
        })

    }, 1000);

    // display verification section numbers box
    setTimeout(() => {
      const digitInputVerCode = document.querySelector(".verification_section_numbers_box");
      if (digitInputVerCode) {
        digitInputVerCode.style.display = "flex";
      }
    }, 2000);
  });


  // Verification
  document.getElementById("verify_code").addEventListener("click", function () {
    let phoneNumber = document.getElementById("phone_number").value.trim();
    let enteredCode = document.getElementById("verification_code").value.trim();

    if (!phoneNumber || !enteredCode) {
      document.getElementById("message").textContent = "Введите код";
      return;
    }

    fetch('/wp-json/custom-auth/v1/verify-code', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: phoneNumber, code: enteredCode })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById("message").textContent = "Вход выполнен";
          setTimeout(() => {
            // Call registration or authorization API after successful verification
            registrationOrAuthorisation(phoneNumber);
          }, 2000);
        } else {
          document.getElementById("message").textContent = "Неверный код";
        }
      })
      .catch(error => {
        document.getElementById("message").textContent = "Ошибка входа";
      });
  });


  // Function to call the backend registration or login API
  function registrationOrAuthorisation(phoneNumber) {
    fetch('/wp-json/custom-auth/v1/registration-or-authorization', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: phoneNumber })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById("message").textContent = data.success;
          setTimeout(() => {
            window.location.href = "/my-account"; // Redirect after success
          }, 2000);
        } else {
          document.getElementById("message").textContent = "Ошибка входа";
        }
      })
      .catch(error => {
        document.getElementById("message").textContent = "Error during registration or login.";
      });
  }


  // display flash call form
  function displayFlashCallForm() {
    const signinFormFlashCallOpenBtn = document.querySelector('.signin_form_flash_call_open_btn button');
    if (signinFormFlashCallOpenBtn) {
      signinFormFlashCallOpenBtn.addEventListener('click', () => {

        const flashCallOpenForm = document.querySelector('.signin_form_flash_call_open_btn');
        flashCallOpenForm.style.display = "none";

        const signinFormFlashCallForm = document.querySelector('.signin_form_flash_call');
        signinFormFlashCallForm.style.display = "flex";
      })
    }
  }
  displayFlashCallForm();

});
