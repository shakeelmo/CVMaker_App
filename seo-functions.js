// SEO Functions for Admin Dashboard

async function loadSeoSection() {
    const tbody = document.getElementById('seo-table-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading SEO data...</td></tr>';

    try {
        const response = await fetch('/api/admin.php?action=seo', {
            headers: { 'Authorization': `Bearer ${localStorage.getItem('admin_token')}` }
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Failed to load SEO data');

        const items = data.items || [];
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No pages found.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const typeBadge = item.kind === 'static' 
                ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Static</span>' 
                : item.kind === 'page' 
                    ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Page</span>' 
                    : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Blog</span>';
            const titleLen = (item.meta_title || '').length;
            const descLen = (item.meta_description || '').length;
            const titleColor = titleLen > 60 ? 'text-red-600' : titleLen > 0 ? 'text-gray-700' : 'text-gray-400';
            const descColor = descLen > 160 ? 'text-red-600' : descLen > 0 ? 'text-gray-700' : 'text-gray-400';
            const titleDisplay = item.meta_title 
                ? `${item.meta_title} <span class="text-xs text-gray-400">(${titleLen})</span>` 
                : '<em class="text-gray-400">Not set</em>';
            const descDisplay = item.meta_description 
                ? `${item.meta_description.substring(0, 60)}${item.meta_description.length > 60 ? '...' : ''} <span class="text-xs text-gray-400">(${descLen})</span>` 
                : '<em class="text-gray-400">Not set</em>';
            
            return `
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-700">${item.label}</td>
                    <td class="px-4 py-3">${typeBadge}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">${item.url}</td>
                    <td class="px-4 py-3 text-sm ${titleColor}">${titleDisplay}</td>
                    <td class="px-4 py-3 text-sm ${descColor}">${descDisplay}</td>
                    <td class="px-4 py-3">
                        <button onclick="editSeoItem('${item.kind}', ${item.id || 0}, '${(item.meta_title || '').replace(/'/g, "\\'")}', '${(item.meta_description || '').replace(/'/g, "\\'")}', '${item.url}')" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${error.message}</td></tr>`;
    }
}

function editSeoItem(kind, id, metaTitle, metaDesc, url) {
    const modal = document.createElement('div');
    modal.id = 'seo-edit-modal';
    modal.className = 'fixed inset-0 bg-black/50 z-50 flex items-center justify-center';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-800">Edit SEO: ${url}</h3>
                <button onclick="document.getElementById('seo-edit-modal').remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                    <input type="text" id="seo-edit-title" value="${metaTitle.replace(/"/g, '&quot;')}" maxlength="70" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter meta title">
                    <p class="text-xs text-gray-400 mt-1">Recommended: 50-60 characters. <span id="seo-title-count" class="font-medium">${metaTitle.length}/70</span></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                    <textarea id="seo-edit-desc" rows="3" maxlength="170" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter meta description">${metaDesc}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Recommended: 150-160 characters. <span id="seo-desc-count" class="font-medium">${metaDesc.length}/170</span></p>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button onclick="document.getElementById('seo-edit-modal').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button onclick="saveSeoItem('${kind}', ${id}, '${url}')" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
            </div>
            <div id="seo-save-message" class="hidden mt-3 px-4 py-3 rounded-lg text-sm font-medium"></div>
        </div>
    `;
    document.body.appendChild(modal);

    const titleInput = modal.querySelector('#seo-edit-title');
    const descInput = modal.querySelector('#seo-edit-desc');
    titleInput.addEventListener('input', () => {
        document.getElementById('seo-title-count').textContent = `${titleInput.value.length}/70`;
    });
    descInput.addEventListener('input', () => {
        document.getElementById('seo-desc-count').textContent = `${descInput.value.length}/170`;
    });
}

async function saveSeoItem(kind, id, url) {
    const metaTitle = document.getElementById('seo-edit-title').value.trim();
    const metaDesc = document.getElementById('seo-edit-desc').value.trim();
    const msgBox = document.getElementById('seo-save-message');

    try {
        const response = await fetch('/api/admin.php?action=seo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
            },
            body: JSON.stringify({ kind, id, url, meta_title: metaTitle, meta_description: metaDesc })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Failed to save');

        if (msgBox) {
            msgBox.className = 'mt-3 px-4 py-3 rounded-lg text-sm font-medium bg-green-100 text-green-700';
            msgBox.textContent = 'SEO settings saved!';
            msgBox.classList.remove('hidden');
        }
        setTimeout(() => { document.getElementById('seo-edit-modal')?.remove(); loadSeoSection(); }, 1000);
    } catch (error) {
        if (msgBox) {
            msgBox.className = 'mt-3 px-4 py-3 rounded-lg text-sm font-medium bg-red-100 text-red-700';
            msgBox.textContent = error.message;
            msgBox.classList.remove('hidden');
        }
    }
}

async function regenerateSitemap() {
    try {
        const response = await fetch('/api/admin.php?action=seo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
            },
            body: JSON.stringify({ mode: 'regenerate-sitemap' })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Failed to regenerate sitemap');

        const msgBox = document.getElementById('seo-message');
        if (msgBox) {
            msgBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-green-100 text-green-700';
            msgBox.textContent = 'Sitemap regenerated successfully!';
            msgBox.classList.remove('hidden');
            setTimeout(() => msgBox.classList.add('hidden'), 3000);
        }
    } catch (error) {
        const msgBox = document.getElementById('seo-message');
        if (msgBox) {
            msgBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-100 text-red-700';
            msgBox.textContent = error.message;
            msgBox.classList.remove('hidden');
        }
    }
}
