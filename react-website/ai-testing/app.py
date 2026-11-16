"""
AI Testing — Floor Plan → Complete Interior Design
==================================================

Upload a floor plan (or empty room photo) and the app generates a
high-quality, fully furnished interior design render using a
ControlNet-guided Stable Diffusion model hosted on Replicate.

Flow:
  1. User uploads an image (floor plan / room photo)
  2. Picks a design style + room type + creativity level
  3. AI generates the final photorealistic interior design
  4. Before / after shown side by side, result downloadable

Run:
  export REPLICATE_API_TOKEN=r8_xxxxxxxxxxxxxxxx   # https://replicate.com/account
  pip install -r requirements.txt
  python app.py
  → opens http://127.0.0.1:7860
"""

import io
import os
import time

import gradio as gr
import replicate
import requests
from PIL import Image

# ─────────────────────────────────────────────────────────────────────────────
# Model — ControlNet (MLSD / structure-aware) interior design on Replicate.
# The structure of the input image (walls, layout) is preserved while the
# model "furnishes" and decorates it photorealistically.
# ─────────────────────────────────────────────────────────────────────────────
MODEL = "adirik/interior-design"

# Design style presets → prompt fragments tuned for quality
STYLES = {
    "Modern": "modern interior design, clean lines, neutral palette, designer furniture",
    "Minimalist": "minimalist interior design, uncluttered, simple forms, soft natural light",
    "Scandinavian": "scandinavian interior design, light wood, white walls, cozy hygge textiles",
    "Luxury": "luxury interior design, marble surfaces, brass accents, statement lighting, elegant",
    "Industrial": "industrial loft interior design, exposed brick, black steel, concrete floor",
    "Bohemian": "bohemian interior design, layered rugs, plants, rattan, warm earthy colors",
    "Japandi": "japandi interior design, japanese minimalism, natural oak, low furniture, zen",
    "Classic": "classic traditional interior design, crown molding, warm wood, refined furniture",
}

ROOMS = [
    "full apartment", "living room", "bedroom", "kitchen",
    "bathroom", "dining room", "home office", "kids room",
]

NEGATIVE_PROMPT = (
    "lowres, watermark, banner, logo, text, deformed, blurry, out of focus, "
    "surreal, ugly, beginner, distorted walls, warped layout, bad architecture"
)


def _build_prompt(style: str, room: str) -> str:
    return (
        f"A {STYLES[style]}, fully furnished {room}, complete interior design, "
        "photorealistic, professional architectural visualization, "
        "interior design magazine photography, ultra detailed, natural lighting, "
        "8k, high quality"
    )


def generate(image: Image.Image, style: str, room: str, creativity: float,
             steps: int, progress=gr.Progress()):
    """Floor plan / room photo → furnished interior design render."""
    if image is None:
        raise gr.Error("Please upload a floor plan or room image first.")
    if not os.environ.get("REPLICATE_API_TOKEN"):
        raise gr.Error(
            "REPLICATE_API_TOKEN is not set.\n"
            "Get a token at https://replicate.com/account and run:\n"
            "  export REPLICATE_API_TOKEN=r8_xxx"
        )

    progress(0.05, desc="Preparing image…")

    # Keep uploads reasonable: long side ≤ 1536 px (model works best ≤ 1536)
    img = image.convert("RGB")
    long_side = max(img.size)
    if long_side > 1536:
        scale = 1536 / long_side
        img = img.resize(
            (int(img.width * scale), int(img.height * scale)),
            Image.LANCZOS,
        )

    buf = io.BytesIO()
    img.save(buf, format="PNG")
    buf.seek(0)

    progress(0.15, desc="Generating interior design… (30–60s)")
    t0 = time.time()

    output = replicate.run(
        MODEL,
        input={
            "image": buf,
            "prompt": _build_prompt(style, room),
            "negative_prompt": NEGATIVE_PROMPT,
            # How much the AI may redesign vs. preserve the input structure
            "prompt_strength": float(creativity),
            "num_inference_steps": int(steps),
            "guidance_scale": 15,
        },
    )

    # Output is a URL (or list of URLs) to the generated image
    url = output[0] if isinstance(output, (list, tuple)) else str(output)
    progress(0.9, desc="Downloading result…")
    resp = requests.get(url, timeout=120)
    resp.raise_for_status()
    result = Image.open(io.BytesIO(resp.content))

    elapsed = time.time() - t0
    return result, f"✅ Done in {elapsed:.1f}s — {style} {room} ({result.width}×{result.height})"


# ─────────────────────────────────────────────────────────────────────────────
# UI
# ─────────────────────────────────────────────────────────────────────────────
CSS = """
.gradio-container {max-width: 1100px !important; margin: auto;}
#title {text-align: center;}
#result-img img {border-radius: 12px;}
#input-img img {border-radius: 12px;}
"""

with gr.Blocks(title="AI Interior Designer — Floor Plan Testing",
               theme=gr.themes.Soft(primary_hue="blue"), css=CSS) as demo:
    gr.Markdown(
        """
        # 🏠 AI Interior Designer — Testing
        Upload a **floor plan** (or an empty room photo), pick a style, and get a
        **complete, high-quality interior design** render. The room layout is
        preserved; the AI furnishes and decorates it.
        """,
        elem_id="title",
    )

    with gr.Row():
        with gr.Column(scale=1):
            inp = gr.Image(label="1 · Floor plan / room image",
                           type="pil", elem_id="input-img")
            style = gr.Dropdown(list(STYLES.keys()), value="Modern",
                                label="2 · Design style")
            room = gr.Dropdown(ROOMS, value="full apartment",
                               label="3 · Room type")
            creativity = gr.Slider(
                0.4, 1.0, value=0.8, step=0.05,
                label="Creativity",
                info="Low = stay close to the input · High = bolder redesign",
            )
            steps = gr.Slider(
                20, 50, value=35, step=5,
                label="Quality (inference steps)",
                info="More steps = higher quality, slower",
            )
            btn = gr.Button("✨ Generate Interior Design",
                            variant="primary", size="lg")

        with gr.Column(scale=1):
            out = gr.Image(label="Final result", elem_id="result-img",
                           format="png", interactive=False)
            status = gr.Markdown("")

    btn.click(generate, [inp, style, room, creativity, steps], [out, status])

    gr.Markdown(
        """
        ---
        **Tips for best results**
        - Clean, high-contrast floor plans work best (black walls on white)
        - For photos: shoot straight-on, well lit, empty or sparsely furnished rooms
        - `Creativity 0.75–0.85` keeps the layout while fully furnishing it
        """
    )

if __name__ == "__main__":
    demo.launch()
