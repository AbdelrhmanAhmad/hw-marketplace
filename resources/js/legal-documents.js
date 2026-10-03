// إفلاس تك — المرحلة 3: توليد PDF/Word للمستندات القانونية بالكامل داخل
// المتصفح (نفس أسلوب hw-eflas المُثبَت — html2canvas يلتقط عنصر DOM بصريًا،
// docx يبني الفقرات من نص عادي). لا خادم PDF/Word — تفادي مشاكل تشكيل
// الحروف العربية عند التوليد بجهة الخادم.
//
// كل المكتبات (html2canvas/jspdf/docx/signature_pad) تُحمَّل ديناميكيًا
// (import() كسول) — لا تُضاف لحزمة app.js الأساسية المحمَّلة بكل صفحات
// المنصة، فقط عند فتح تبويب "المستندات القانونية" فعليًا (نفس أسلوب
// hw-eflas الأصلي حرفيًا، case-workspace.tsx: `await import("html2canvas")`).

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function downloadPdf(paperEl, filename) {
    if (!paperEl) return;

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import('html2canvas'),
        import('jspdf'),
    ]);

    await document.fonts.ready;

    const canvas = await html2canvas(paperEl, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
    });

    const imgData = canvas.toDataURL('image/jpeg', 1.0);
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pdfW = pdf.internal.pageSize.getWidth();
    const pdfH = pdf.internal.pageSize.getHeight();
    const imgH = (canvas.height / canvas.width) * pdfW;

    if (imgH <= pdfH + 1) {
        pdf.addImage(imgData, 'JPEG', 0, 0, pdfW, imgH);
    } else {
        let offsetY = 0;
        while (offsetY < imgH) {
            if (offsetY > 0) pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, -offsetY, pdfW, imgH);
            offsetY += pdfH;
        }
    }

    pdf.save(filename);
}

export async function downloadDocx(text, debtorName, filename) {
    const { Document, Packer, Paragraph, TextRun, AlignmentType, HeadingLevel, BorderStyle } = await import('docx');

    const lines = (text || '').split('\n').filter(Boolean);

    const doc = new Document({
        sections: [{
            properties: {
                page: {
                    size: { width: 11906, height: 16838 },
                    margin: { top: 1134, bottom: 1134, left: 1276, right: 1276 },
                },
            },
            children: [
                new Paragraph({
                    text: 'المملكة العربية السعودية',
                    alignment: AlignmentType.CENTER,
                    bidirectional: true,
                    heading: HeadingLevel.HEADING_2,
                    border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: '9A7432' } },
                }),
                new Paragraph({
                    children: [new TextRun({ text: debtorName || 'اسم الشركة', bold: true, size: 28 })],
                    alignment: AlignmentType.CENTER,
                    bidirectional: true,
                    spacing: { after: 300 },
                }),
                ...lines.map((line) => new Paragraph({
                    children: [new TextRun({ text: line, bidirectional: true })],
                    alignment: AlignmentType.RIGHT,
                    bidirectional: true,
                    spacing: { after: 160 },
                })),
            ],
        }],
    });

    const blob = await Packer.toBlob(doc);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

/**
 * توقيع حقيقي مرسوم بالفأرة/اللمس — لا علاقة بأي تحقق هوية (لا Nafath وهمي).
 *
 * تنبيه: لا تُستخدَم offsetWidth/offsetHeight هنا عمدًا — هذا الـCanvas قد
 * يُهيَّأ بينما تبويبه الأب مخفي (display:none عبر x-show، لأن Alpine يُهيّئ
 * كل x-data بالصفحة عند التحميل بغضّ النظر عن ظهوره)، وعنصر مخفي offsetWidth
 * له صفر دائمًا — يُنتِج Canvas بحجم صفر يبتلع كل رسم بصمت. الحجم الاسمي
 * (عرض/ارتفاع HTML الصريحين بالـBlade) ثابت بغض النظر عن حالة الظهور.
 */
export async function attachSignaturePad(canvasEl) {
    const { default: SignaturePad } = await import('signature_pad');

    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const width = canvasEl.width || 400;
    const height = canvasEl.height || 150;
    canvasEl.width = width * ratio;
    canvasEl.height = height * ratio;
    canvasEl.style.width = `${width}px`;
    canvasEl.style.height = `${height}px`;
    canvasEl.getContext('2d').scale(ratio, ratio);

    return new SignaturePad(canvasEl, { backgroundColor: '#ffffff' });
}

export async function saveSignature(url, role, dataUrl) {
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ role, data_url: dataUrl }),
    });

    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message || 'فشل حفظ التوقيع.');
    }

    return res.json();
}

window.LegalDocuments = { downloadPdf, downloadDocx, attachSignaturePad, saveSignature };
