# Converting Markdown to DOCX for Paper Submission

The paper document `heytrisha-paper.md` has been created in Markdown format. To convert it to DOCX format for journal submission, you can use one of the following methods:

## Method 1: Using Pandoc (Recommended)

Pandoc is a universal document converter that produces high-quality DOCX files.

### Installation

**Windows:**
```powershell
# Using Chocolatey
choco install pandoc

# Or download from: https://github.com/jgm/pandoc/releases
```

**macOS:**
```bash
brew install pandoc
```

**Linux:**
```bash
sudo apt-get install pandoc
```

### Conversion Command

```bash
cd wp-content/plugins/heytrisha-woo/doc
pandoc heytrisha-paper.md -o heytrisha-paper.docx --reference-doc=softwarex-osp-template.docx
```

If you want to use the template formatting:
```bash
pandoc heytrisha-paper.md -o heytrisha-paper.docx --reference-doc=softwarex-osp-template.docx -s
```

## Method 2: Using Microsoft Word

1. Open Microsoft Word
2. Go to **File** → **Open** → Select `heytrisha-paper.md`
3. Word will automatically convert the markdown
4. Apply formatting from `softwarex-osp-template.docx`:
   - Copy styles from the template
   - Adjust fonts, spacing, and layout
5. Save as `.docx`

## Method 3: Using Online Converters

1. Visit https://www.markdowntoword.com/ or similar service
2. Upload `heytrisha-paper.md`
3. Download the converted DOCX file
4. Open in Word and apply template formatting

## Method 4: Using VS Code Extension

1. Install "Markdown PDF" extension in VS Code
2. Open `heytrisha-paper.md`
3. Right-click → "Markdown PDF: Export (docx)"
4. Apply template formatting in Word

## Formatting Tips

After conversion, ensure the document matches the template:

1. **Title**: Bold, Italic, centered
2. **Author**: Regular text with email underlined
3. **Abstract**: Bold italic heading, regular text
4. **Keywords**: Bold italic heading, comma-separated
5. **Section Headings**: Bold italic (1., 2., 3., etc.)
6. **Subsection Headings**: Bold italic (1.1, 2.1, etc.)
7. **Code Blocks**: Use monospace font, proper indentation
8. **References**: Follow journal citation format

## Template Reference

The template file `softwarex-osp-template.docx` contains the required formatting styles. When converting, reference this template to maintain consistency with journal requirements.



