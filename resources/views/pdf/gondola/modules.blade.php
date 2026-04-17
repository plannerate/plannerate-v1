<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Gôndola {{ $gondola->name }} - Planograma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Instrument Sans', Arial, sans-serif;
            background-color: #ffffff;
        }
    </style>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body style="display: flex; justify-content: center; align-items: center; flex-direction: column; width: 100%;">
    
    <div style="position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; gap: 10px;">
        <button id="generatePdfBtn" style="padding: 10px 20px; background-color: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            📄 Gerar PDF
        </button>
        <button id="autoGenerateBtn" style="padding: 10px 20px; background-color: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            ⚡ Gerar e Baixar
        </button>
    </div>
    @foreach ($sections as $section)
    @php
    $cremalheiraWidth = ($section->cremalheira_width ?? 0) * $gondola->scale_factor;
    $sectionWidth = $section->width * $gondola->scale_factor;
    $sectionHeight = $section->height * $gondola->scale_factor;
    @endphp
    <div data-module-section="{{ $section->id }}" data-module-order="{{ $section->ordering }}" style="position: relative; margin-top: 96px; width: {{$sectionWidth + $cremalheiraWidth * 2}}px; height: {{$sectionHeight}}px; margin-left: 0px;">
        @include('pdf.gondola.cremalheira-l', ['section' => $section, 'gondola' => $gondola, 'sectionWidth' => $sectionWidth, 'sectionHeight' => $sectionHeight])

        @if($shelves = $section->shelves)
        @foreach($shelves as $shelf)
        @include('pdf.gondola.shelf', ['shelf' => $shelf, 'section' => $section, 'gondola' => $gondola, 'sectionWidth' => $sectionWidth, 'sectionHeight' => $sectionHeight, 'cremalheiraWidth' => $cremalheiraWidth])
        @endforeach
        @endif
        <div style="position: absolute; bottom: 0; left: 0; display: flex; width: 100%; align-items: center; justify-content: center;">
            <div style="font-size: 0.75rem; color: #64748b;"> Módulo #{{ $section->ordering }}</div>
        </div>
        @include('pdf.gondola.cremalheira-r', ['section' => $section, 'gondola' => $gondola , 'sectionWidth' => $sectionWidth, 'sectionHeight' => $sectionHeight, 'cremalheiraWidth' => $cremalheiraWidth])
    </div>

    <div style="page-break-after: always;"></div>
    @endforeach

    <script>
        const { jsPDF } = window.jspdf;

        async function generatePDF(autoDownload = false) {
            const btn = autoDownload ? document.getElementById('autoGenerateBtn') : document.getElementById('generatePdfBtn');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Gerando...';
            btn.disabled = true;

            try {
                const modules = document.querySelectorAll('[data-module-section]');
                
                if (modules.length === 0) {
                    alert('Nenhum módulo encontrado!');
                    return;
                }

                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();

                for (let i = 0; i < modules.length; i++) {
                    btn.textContent = `⏳ Gerando ${i + 1}/${modules.length}...`;
                    
                    const module = modules[i];
                    
                    // Captura o módulo como canvas
                    const canvas = await html2canvas(module, {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#ffffff',
                        logging: false
                    });

                    const imgData = canvas.toDataURL('image/png', 0.95);
                    const imgWidth = canvas.width;
                    const imgHeight = canvas.height;
                    const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
                    const imgX = (pdfWidth - imgWidth * ratio) / 2;
                    const imgY = 5;

                    // Adiciona nova página se não for a primeira
                    if (i > 0) {
                        pdf.addPage();
                    }

                    pdf.addImage(
                        imgData,
                        'PNG',
                        imgX,
                        imgY,
                        imgWidth * ratio,
                        imgHeight * ratio
                    );
                }

                const filename = 'gondola_{{ $gondola->name }}_' + new Date().toISOString().split('T')[0] + '.pdf';
                
                if (autoDownload) {
                    pdf.save(filename);
                    btn.textContent = '✅ Baixado!';
                } else {
                    // Abre em nova aba
                    window.open(pdf.output('bloburl'), '_blank');
                    btn.textContent = '✅ Gerado!';
                }

                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);

            } catch (error) {
                console.error('Erro ao gerar PDF:', error);
                alert('Erro ao gerar PDF: ' + error.message);
                btn.textContent = '❌ Erro';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);
            }
        }

        document.getElementById('generatePdfBtn').addEventListener('click', () => generatePDF(false));
        document.getElementById('autoGenerateBtn').addEventListener('click', () => generatePDF(true));

        // Gerar automaticamente ao carregar (opcional - descomente se quiser)
        // window.addEventListener('load', () => {
        //     setTimeout(() => generatePDF(true), 1000);
        // });
    </script>

</body>

</html>