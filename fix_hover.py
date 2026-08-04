import sys

with open("/home/hamid/Downloads/azuronn_2/assets/css/style.css", "r") as f:
    lines = f.readlines()

new_css = """
/* Card text */
.prod-cat {
  font-size: 10px;
  color: #999;
  margin-bottom: 8px;
  font-weight: 400;
  text-transform: lowercase;
}

.prod-name {
  font-family: var(--serif);
  font-weight: 700;
  font-size: 16px;
  color: #222;
  margin-bottom: 12px;
}

.prod-prices .old {
  color: #999;
  text-decoration: line-through;
  margin-right: 6px;
  font-size: 13px;
  font-weight: 400;
}

.prod-prices .new {
  color: #b18861;
  font-weight: 600;
  font-size: 14px;
}

/* ══ SMOOTH HOVER REPLICA ══ */
.prod-card {
  position: relative;
  text-align: center;
  background: transparent;
  transition: all 0.3s ease;
  z-index: 1;
}

.prod-card:hover {
  z-index: 10;
}

/* The popup container overlays the bottom part and extends downwards */
.qv-popup {
  position: absolute;
  top: 0;
  left: -2px;
  right: -2px;
  background: #fff;
  border: 2px solid #333;
  padding: 15px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  z-index: 200;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transform: translateY(-10px);
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  display: flex;
  flex-direction: column;
}

.prod-card:hover .qv-popup {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
  transform: translateY(0);
}

/* On hover, swap product images from the main box. We hide the inner popup img */
.prod-card:hover .img-default {
  opacity: 0;
  transform: scale(0.95);
}
.prod-card:hover .img-hover {
  opacity: 1;
  transform: scale(1);
}

.qv-popup-img {
  display: none; /* Rely on the card's native image behind it, OR we recreate it here */
}

/* Wait, to mimic the screenshot perfectly, the popup covers the ENTIRE card. 
   So we should SHOW the popup image, and swap IT on hover! 
   Actually, the screenshot shows the box bounds the image AND the text.
   Let's just make the .qv-popup overlay the whole card! */
.qv-popup {
  /* Overlay entire card */
  top: -20px;
  bottom: auto; /* Allow content to stretch it */
  left: -15px;
  right: -15px;
}

.qv-popup-img {
  display: block;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 15px;
  position: relative;
}
.qv-popup-img img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.qv-popup-body {
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* 
  Actually, the user's products.php sets `img src` inside `qv-popup-img` as the DEFAULT image.
  We want the hover image to show on hover! 
  But wait, `qv-popup-img` only has one `<img>` tag in `products.php`.
  So it's better to just make the `.prod-card` ITSELF get the border and background on hover, 
  and position the `.qv-btn-bar`, stars, desc, actions BELOW the normal content!
*/

"""

# Write script to safely replace lines 572-738
