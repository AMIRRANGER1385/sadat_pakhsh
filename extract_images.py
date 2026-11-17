import fitz, os
pdf=r'D:\\sadat_pakhsh\\source_resume.pdf'
outdir=r'D:\\sadat_pakhsh\\resume_assets'
os.makedirs(outdir, exist_ok=True)
doc=fitz.open(pdf)
count=0
largest=(0,None)
for pno,page in enumerate(doc):
    for img in page.get_images(full=True):
        xref=img[0]
        base=doc.extract_image(xref)
        ext=base.get('ext','png')
        data=base['image']
        path=os.path.join(outdir,f'img_p{pno+1}_{xref}.{ext}')
        with open(path,'wb') as f:
            f.write(data)
        count+=1
        if len(data)>largest[0]:
            largest=(len(data),path)
print('images',count)
print('largest',largest[1],largest[0])
