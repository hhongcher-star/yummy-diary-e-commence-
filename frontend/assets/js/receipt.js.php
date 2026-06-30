
const paymentModal = document.getElementById("paymentModal");
const imagePreview = document.getElementById("imagePreview");
const isPendingReceipt = <?= $isPendingReceipt ? 'true' : 'false' ?>;
const orderNumber = <?= json_encode($order_number) ?>;
const accessToken = <?= json_encode($access_token) ?>;
let paymentConfirmed = !isPendingReceipt;

document.getElementById("openPayment").addEventListener("click", async () => {
  const button = document.getElementById("openPayment");
  button.disabled = true;

  try {
    if (!paymentConfirmed) {
      const formData = new FormData();
      formData.append("order_number", orderNumber);
      formData.append("token", accessToken);

      const response = await fetch(<?= json_encode(appUrl('frontend/api/confirm_payment.php')) ?>, {
        method: "POST",
        body: formData
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.msg || data.message || "è®°å½•è®¢å•å¤±è´¥");
      }

      paymentConfirmed = true;
    }

    paymentModal.classList.add("show");
    document.body.style.overflow = "hidden";
  } catch (error) {
    alert("âŒ " + error.message);
  } finally {
    button.disabled = false;
  }
});

function closePaymentModal(){
  paymentModal.classList.remove("show");
  document.body.style.overflow = "";
}

document.getElementById("closePayment").addEventListener("click", closePaymentModal);
paymentModal.addEventListener("click", event => {
  if(event.target === paymentModal) closePaymentModal();
});

document.getElementById("paymentImage").addEventListener("click", () => {
  imagePreview.classList.add("show");
});

function closeImagePreview(){
  imagePreview.classList.remove("show");
}

document.getElementById("closePreview").addEventListener("click", closeImagePreview);
imagePreview.addEventListener("click", event => {
  if(event.target === imagePreview || event.target.tagName === "IMG") closeImagePreview();
});


