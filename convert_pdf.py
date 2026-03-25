import fitz
import sys

def pdf_to_images(pdf_path):
    try:
        doc = fitz.open(pdf_path)
        for i in range(len(doc)):
            page = doc.load_page(i)
            pix = page.get_pixmap(dpi=200)
            output_path = f"pdf_design_page_{i}.png"
            pix.save(output_path)
            print(f"Saved {output_path}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    pdf_to_images("ilink insurance website-1.pdf")
