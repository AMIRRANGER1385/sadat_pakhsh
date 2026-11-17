import fitz
from pathlib import Path

out_pdf = Path(r'D:\\sadat_pakhsh\\Seyed_Amirmahdi_Hosseini_CV.pdf')
photo = Path(r'D:\\sadat_pakhsh\\resume_assets\\img_p1_6.jpeg')

doc = fitz.open()
page = doc.new_page(width=595, height=842)  # A4

# Layout helpers
margin = 40
x = margin
y = 38
content_width = 595 - 2 * margin

# Header
name = 'SEYED AMIRMAHDI HOSSEINI'
role = 'Computer Engineering Student | AI & LLM Enthusiast'
contact = 'Iran | +98 919 038 5078 | amh58311385@gmail.com'

page.insert_text((x, y), name, fontsize=18, fontname='helv', fill=(0,0,0))
y += 24
page.insert_text((x, y), role, fontsize=11, fontname='helv', fill=(0.2,0.2,0.2))
y += 18
page.insert_text((x, y), contact, fontsize=10, fontname='helv', fill=(0.2,0.2,0.2))

# Photo
if photo.exists():
    rect = fitz.Rect(470, 30, 555, 115)
    page.insert_image(rect, filename=str(photo))

# line
page.draw_line(fitz.Point(margin, 122), fitz.Point(555, 122), color=(0.75,0.75,0.75), width=1)

y = 142

def section(title, y):
    page.insert_text((x, y), title.upper(), fontsize=11, fontname='helv', fill=(0.05,0.2,0.45))
    return y + 16

# Summary
y = section('Professional Summary', y)
summary = (
    'Motivated Computer Engineering student at the University of Tehran with strong interest in '
    'machine learning, large language models, and educational AI systems. Experienced in building '
    'LLM-based applications using RAG, embeddings, vector databases, prompt engineering, and '
    'agent-style workflows. Strong analytical and mathematical foundation with hands-on implementation focus.'
)
rect = fitz.Rect(x, y, 555, y+70)
page.insert_textbox(rect, summary, fontsize=10, fontname='helv', color=(0,0,0), align=0, lineheight=1.3)
y += 82

# Education
y = section('Education', y)
edu = (
    'University of Tehran\n'
    'B.Sc. in Computer Engineering (Software)\n'
    'GPA: 18.6/20'
)
rect = fitz.Rect(x, y, 555, y+54)
page.insert_textbox(rect, edu, fontsize=10, fontname='helv', color=(0,0,0), lineheight=1.3)
y += 66

# Projects
y = section('Selected Projects', y)

p1_title = 'YAAR — Curriculum-Grounded Agentic Tutor for Iranian K-12 Students'
p1_body = (
    '- Built an OpenAI-compatible model endpoint with strict curriculum grounding and grade/subject filters.\n'
    '- Implemented scenario-driven architecture (29 educational scenarios) using YAML-based configuration.\n'
    '- Designed retrieval + verification flow with citation-aware answers and safe fallback behavior.\n'
    '- Integrated Qdrant/Redis-backed pipelines for ingestion, retrieval, and conversational context.'
)
rect = fitz.Rect(x, y, 555, y+18)
page.insert_textbox(rect, p1_title, fontsize=10.5, fontname='helv', color=(0,0,0))
y += 18
rect = fitz.Rect(x, y, 555, y+72)
page.insert_textbox(rect, p1_body, fontsize=9.5, fontname='helv', color=(0,0,0), lineheight=1.25)
y += 78

p2_title = 'RAG & LLM Application Prototypes'
p2_body = (
    '- Built end-to-end RAG pipelines: document ingestion, chunking, embedding, indexing, retrieval, and generation.\n'
    '- Tuned prompts and retrieval settings to improve answer quality and reduce hallucinations.\n'
    '- Developed practical AI-agent workflows using API-based models for real-world tasks.'
)
page.insert_textbox(fitz.Rect(x, y, 555, y+18), p2_title, fontsize=10.5, fontname='helv', color=(0,0,0))
y += 18
page.insert_textbox(fitz.Rect(x, y, 555, y+56), p2_body, fontsize=9.5, fontname='helv', color=(0,0,0), lineheight=1.25)
y += 62

# Skills
y = section('Technical Skills', y)
skills = (
    'Programming: Python, C, C++, R, Verilog\n'
    'AI/ML: Machine Learning, Deep Learning (fundamentals), LLM APIs, RAG, Embeddings, Prompt Engineering\n'
    'Tools: FastAPI, LlamaIndex, Qdrant, Redis, Docker, Git, VS Code, OpenCode, ModelSim, Quartus\n'
    'Languages: Persian (Native), English (Professional Working Proficiency)'
)
page.insert_textbox(fitz.Rect(x, y, 555, y+88), skills, fontsize=9.5, fontname='helv', color=(0,0,0), lineheight=1.3)

# Footer
page.insert_text((margin, 816), 'Updated: Nov 2026', fontsize=8, fontname='helv', fill=(0.45,0.45,0.45))

doc.save(str(out_pdf))
print(str(out_pdf))
