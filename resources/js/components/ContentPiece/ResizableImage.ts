import Image from '@tiptap/extension-image';
import { mergeAttributes } from '@tiptap/core';

export const ResizableImage = Image.extend({
    name: 'resizableImage',

    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
                parseHTML: (element) => element.getAttribute('width'),
                renderHTML: (attributes) => {
                    if (!attributes.width) {
                        return {};
                    }
                    return {
                        width: attributes.width,
                    };
                },
            },
            height: {
                default: null,
                parseHTML: (element) => element.getAttribute('height'),
                renderHTML: (attributes) => {
                    if (!attributes.height) {
                        return {};
                    }
                    return {
                        height: attributes.height,
                    };
                },
            },
        };
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const container = document.createElement('div');
            container.classList.add('image-resizer');
            container.setAttribute('data-drag-handle', '');

            const img = document.createElement('img');
            img.src = node.attrs.src;
            img.alt = node.attrs.alt || '';
            img.title = node.attrs.title || '';

            if (node.attrs.width) {
                img.style.width = node.attrs.width;
            }
            if (node.attrs.height) {
                img.style.height = node.attrs.height;
            }

            img.classList.add('tiptap-image');

            const resizeHandle = document.createElement('div');
            resizeHandle.classList.add('resize-handle');
            resizeHandle.contentEditable = 'false';

            let isResizing = false;
            let startX = 0;
            let startWidth = 0;

            const startResize = (e: MouseEvent) => {
                e.preventDefault();
                e.stopPropagation();

                if (typeof getPos === 'function') {
                    editor.commands.setNodeSelection(getPos());
                }

                isResizing = true;
                startX = e.clientX;
                startWidth = img.offsetWidth;

                document.addEventListener('mousemove', resize);
                document.addEventListener('mouseup', stopResize);
            };

            const resize = (e: MouseEvent) => {
                if (!isResizing) return;

                const diff = e.clientX - startX;
                const newWidth = Math.max(100, startWidth + diff);

                img.style.width = `${newWidth}px`;
                img.style.height = 'auto';
            };

            const stopResize = () => {
                if (!isResizing) return;
                isResizing = false;

                document.removeEventListener('mousemove', resize);
                document.removeEventListener('mouseup', stopResize);

                const width = img.style.width;
                const height = img.style.height;

                if (typeof getPos === 'function' && editor.isEditable) {
                    editor
                        .chain()
                        .updateAttributes('resizableImage', {
                            width,
                            height,
                        })
                        .run();
                }
            };

            resizeHandle.addEventListener('mousedown', startResize);

            container.appendChild(img);
            container.appendChild(resizeHandle);

            return {
                dom: container,
                update: (updatedNode) => {
                    if (updatedNode.type.name !== 'resizableImage') {
                        return false;
                    }

                    img.src = updatedNode.attrs.src;
                    img.alt = updatedNode.attrs.alt || '';
                    img.title = updatedNode.attrs.title || '';

                    if (updatedNode.attrs.width) {
                        img.style.width = updatedNode.attrs.width;
                    } else {
                        img.style.width = '';
                    }

                    if (updatedNode.attrs.height) {
                        img.style.height = updatedNode.attrs.height;
                    } else {
                        img.style.height = '';
                    }

                    return true;
                },
                destroy: () => {
                    resizeHandle.removeEventListener('mousedown', startResize);
                    document.removeEventListener('mousemove', resize);
                    document.removeEventListener('mouseup', stopResize);
                },
            };
        };
    },
});
