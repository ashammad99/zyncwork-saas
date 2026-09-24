import React, { useState, useEffect } from "react"
import { useEditor, EditorContent } from "@tiptap/react"
import StarterKit from "@tiptap/starter-kit"
import TextAlign from "@tiptap/extension-text-align"
import BulletList from "@tiptap/extension-bullet-list"
import OrderedList from "@tiptap/extension-ordered-list"
import Heading from "@tiptap/extension-heading"
import Paragraph from "@tiptap/extension-paragraph"

// === Custom Heading with inline styles and proper spacing ===
const CustomHeading = Heading.extend({
  addAttributes() {
    return {
      level: { default: 1 },
      style: {
        default: null,
        renderHTML: attrs => ({
          style: attrs.style,
        }),
        parseHTML: el => ({
          style: el.getAttribute("style"),
        }),
      },
    }
  },
  renderHTML({ node, HTMLAttributes }) {
    const { level } = node.attrs
    const tag = `h${level}`
    return [tag, HTMLAttributes, 0]
  },
})

// === Custom Paragraph with proper spacing ===
const CustomParagraph = Paragraph.extend({
  renderHTML() {
    return ["p", { style: "margin: 0.75rem 0;" }, 0]
  },
})

const CustomBulletList = BulletList.extend({
  renderHTML() {
    return ["ul", { style: "list-style-type: disc; margin-left: 1.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem; padding-left: 1.5rem;" }, 0]
  },
})

const CustomOrderedList = OrderedList.extend({
  renderHTML() {
    return ["ol", { style: "list-style-type: decimal; margin-left: 1.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem; padding-left: 1.5rem;" }, 0]
  },
})

export default function BasicEditor({ value = "", onChange }) {
  const [listType, setListType] = useState("bullet")

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        bulletList: false,
        orderedList: false,
        heading: false,
        paragraph: false,
      }),
      CustomHeading,
      CustomParagraph,
      CustomBulletList,
      CustomOrderedList,
      TextAlign.configure({
        types: ["heading", "paragraph", "bulletList", "orderedList"],
        alignments: ["left", "center", "right", "justify"],
      }),
    ],
    content: value,
    editorProps: {
      attributes: {
        style:
          "font-family:Inter, sans-serif; font-size:15px; line-height:1.6; border:1px solid #d1d5db; border-radius:8px; padding:12px; min-height:200px; background:#fff; outline:none;",
      },
    },
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
  })

  // Sync editor content when value prop changes (e.g., loading initial data)
  useEffect(() => {
    if (editor && value && !editor.isActive("doc")) {
      editor.commands.setContent(value)
    }
  }, [value, editor])

  if (!editor) return null

  const Btn = (label, fn, active = false) => (
    <button
      type="button"
      onClick={fn}
      style={{
        padding: "4px 8px",
        borderRadius: "6px",
        background: active ? "#e5e7eb" : "transparent",
        fontSize: "13px",
      }}
    >
      {label}
    </button>
  )

  // Set heading with inline size
  const setHeading = level => {
    const styles = {
      1: "font-size:28px; font-weight:700; margin:0.5rem 0;",
      2: "font-size:22px; font-weight:600; margin:0.5rem 0;",
      3: "font-size:18px; font-weight:600; margin:0.25rem 0;",
    }
    editor.chain().focus().toggleHeading({ level, style: styles[level] }).run()
  }

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
      {/* Toolbar */}
      <div
        style={{
          display: "flex",
          flexWrap: "wrap",
          gap: "6px",
          padding: "8px",
          border: "1px solid #d1d5db",
          borderRadius: "8px",
          background: "#f9fafb",
        }}
      >
        {Btn("B", () => editor.chain().focus().toggleBold().run(), editor.isActive("bold"))}
        {Btn("I", () => editor.chain().focus().toggleItalic().run(), editor.isActive("italic"))}

        {Btn("H1", () => setHeading(1), editor.isActive("heading", { level: 1 }))}
        {Btn("H2", () => setHeading(2), editor.isActive("heading", { level: 2 }))}
        {Btn("H3", () => setHeading(3), editor.isActive("heading", { level: 3 }))}

        {Btn("UL", () => {
          editor.chain().focus().toggleBulletList().run()
        }, editor.isActive("bulletList"))}

        {Btn("OL", () => {
          editor.chain().focus().toggleOrderedList().run()
        }, editor.isActive("orderedList"))}

        {Btn("Left", () => editor.chain().focus().setTextAlign("left").run(), editor.isActive({ textAlign: "left" }))}
        {Btn("Center", () => editor.chain().focus().setTextAlign("center").run(), editor.isActive({ textAlign: "center" }))}
        {Btn("Right", () => editor.chain().focus().setTextAlign("right").run(), editor.isActive({ textAlign: "right" }))}
        {Btn("Justify", () => editor.chain().focus().setTextAlign("justify").run(), editor.isActive({ textAlign: "justify" }))}
      </div>

      <EditorContent editor={editor} />
    </div>
  )
}
