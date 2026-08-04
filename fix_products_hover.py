import sys

with open("/home/hamid/Downloads/azuronn_2/assets/css/style.css", "r") as f:
    lines = f.readlines()

css_replacement = """
/* ══ SMOOTH HOVER REPLICA ══ */
.prod-card {
  text-align: center;
  position: relative;
  transition: all 0.3s ease;
  z-index: 1;
}

.prod-card::before {
  content: "";
  position: absolute;
  top: -15px;
  left: -15px;
  right: -15px;
  bottom: -230px;
  background: #fff;
  border: 2px solid #111;
  box-shadow: 0 10px 40px rgba(0,0,0,0.1);
  z-index: -1;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  pointer-events: none;
}

.prod-card:hover {
  z-index: 100;
}

.prod-card:hover::before {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
}

/* Hide original text on hover to make room for popup details */
.prod-card .prod-cat,
.prod-card .prod-name,
.prod-card .prod-prices {
  transition: opacity 0.2s;
}
.prod-card:hover .prod-cat,
.prod-card:hover .prod-name,
.prod-card:hover .prod-prices {
  opacity: 0;
}

.prod-img-box {
  position: relative;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 25px;
  overflow: hidden;
}

.prod-img-box .img-default,
.prod-img-box .img-hover {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: opacity .3s ease-in-out, transform .3s ease-in-out;
}

.prod-img-box .img-default {
  opacity: 1;
  transform: scale(1);
}

.prod-img-box .img-hover {
  opacity: 0;
  transform: scale(1.08);
}

.prod-card:hover .img-default {
  opacity: 0;
  transform: scale(0.95);
}

.prod-card:hover .img-hover {
  opacity: 1;
  transform: scale(1);
}

/* ══ QUICK VIEW POPUP (Restored Monsta Design) ══ */
.qv-popup {
  position: absolute;
  top: 220px; /* Just below image */
  left: 0;
  right: 0;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  pointer-events: none;
  z-index: 10;
}

.prod-card:hover .qv-popup {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
  transform: translateY(0);
}

.qv-popup-img {
  display: none !important;
}

.qv-popup-body {
  padding: 0;
  display: flex;
  flex-direction: column;
  text-align: center;
}

.qv-btn-bar {
  display: block;
  width: 100%;
  background: #333;
  color: #fff;
  padding: 12px 0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  margin-bottom: 18px;
  border: none;
  cursor: pointer;
  transition: background .2s;
}

.qv-btn-bar:hover {
  background: var(--gold);
}

.qv-popup-cat, .qv-popup-name, .qv-stars, .qv-desc {
  display: block;
}

.qv-popup-cat {
  font-size: 11px;
  color: #999;
  text-transform: capitalize;
  margin-bottom: 6px;
}

.qv-popup-name {
  font-family: var(--serif);
  font-weight: 700;
  font-size: 16px;
  color: #222;
  margin-bottom: 12px;
}

.qv-stars {
  color: #f7941d;
  font-size: 10px;
  letter-spacing: 2px;
  margin-bottom: 12px;
}

.qv-desc {
  font-size: 12px;
  color: #777;
  line-height: 1.6;
  margin-bottom: 24px;
  padding: 0 5px;
}

.qv-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding-bottom: 10px;
}

.qv-heart,
.qv-list-btn {
  width: 40px;
  height: 40px;
  border: 1px solid #ddd;
  background: #f8f8f8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: #666;
  cursor: pointer;
  flex-shrink: 0;
  transition: all .2s;
}

.qv-heart:hover,
.qv-list-btn:hover {
  border-color: #333;
  background: #333;
  color: #fff;
}

.qv-add-btn {
  flex: 1;
  background: #f4f4f4;
  color: #333;
  padding: 12px 0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  border: 1px solid #ddd;
  cursor: pointer;
  transition: all .2s;
}

.qv-add-btn:hover {
  background: #333;
  border-color: #333;
  color: #fff;
}
"""

with open("/home/hamid/Downloads/azuronn_2/assets/css/style.css", "w") as f:
    # Everything before line 540 (which is .prod-img-box)
    f.writelines(lines[:540])
    # The new CSS
    f.write(css_replacement)
    # The original file starts the next block at 740 /* Tab pane show/hide */
    # We replaced 540 to 739.
    f.writelines(lines[739:])
