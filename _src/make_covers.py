"""Generate branded 1200x630 cover images for Insights posts (used when a post has no WordPress featured image).
Usage: python3 _src/make_covers.py  — needs Pillow and /tmp/inter800.ttf + /tmp/inter500.ttf (instanced from assets/fonts/inter-latin-var.woff2 with fontTools).
Add new posts to POSTS (slug, title, tag)."""
import os, textwrap
from PIL import Image, ImageDraw, ImageFont
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "assets", "img", "insights")
POSTS = [
 ("introducing-taxnify-by-think-mybiz-tech", "Introducing Taxnify: the business and compliance platform built by Think Mybiz Tech", "TAXNIFY", (25, 195, 165)),
 ("business-automation-human-approval", "Business automation with a human in the loop", "AUTOMATION", (47, 109, 246)),
 ("popia-business-software-questions", "POPIA and your business software: questions to ask before you build", "COMPLIANCE", (245, 158, 11)),
 ("signs-your-business-has-outgrown-spreadsheets", "Seven signs your business has outgrown spreadsheets", "SYSTEMS", (139, 92, 246)),
 ("progressive-web-apps-for-business", "Progressive Web Apps: one app for every device, without the app stores", "PWA", (14, 165, 233)),
 ("compliance-first-software-design", "Compliance is where software should start, not where it ends", "COMPLIANCE", (239, 68, 68)),
]
B = ImageFont.truetype("/tmp/inter800.ttf", 58); S = ImageFont.truetype("/tmp/inter500.ttf", 26); T = ImageFont.truetype("/tmp/inter800.ttf", 22)
for slug, title, tag, acc in POSTS:
    W, H = 1200, 630
    img = Image.new("RGB", (W, H)); d = ImageDraw.Draw(img)
    for y in range(H):
        t = y / H; d.line([(0, y), (W, y)], fill=tuple(int(a + (b - a) * t) for a, b in zip((12, 33, 72), (6, 19, 43))))
    d.ellipse((860, -220, 1460, 380), fill=tuple(int(c * .35 + 12 * .65) for c in acc))
    for x in range(0, W, 60): d.line([(x, 0), (x, H)], fill=(20, 40, 78))
    for y in range(0, H, 60): d.line([(0, y), (W, y)], fill=(20, 40, 78))
    d.rounded_rectangle((80, 90, 80 + d.textlength(tag, font=T) + 36, 134), radius=22, fill=acc)
    d.text((98, 99), tag, font=T, fill=(6, 19, 43))
    y = 190
    for line in textwrap.wrap(title, 30)[:4]:
        d.text((80, y), line, font=B, fill="white"); y += 72
    d.rounded_rectangle((80, 532, 120, 572), radius=9, fill="white")
    d.polygon([(91, 544.5), (109, 544.5), (109, 548.7), (102.2, 548.7), (102.2, 561), (97.8, 561), (97.8, 548.7), (91, 548.7)], fill=(10, 29, 63))
    d.text((136, 536), "Think Mybiz Tech  ·  Insights", font=S, fill=(202, 215, 239))
    img.save(os.path.join(OUT, slug + ".png"), optimize=True)
print("covers:", len(POSTS))
