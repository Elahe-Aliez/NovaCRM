(function () {
    function selectClosingResult() {
        return new Promise((resolve) => {
            const overlay = document.createElement("div");
            overlay.style.position = "fixed";
            overlay.style.inset = "0";
            overlay.style.background = "rgba(17, 24, 39, 0.55)";
            overlay.style.display = "flex";
            overlay.style.alignItems = "center";
            overlay.style.justifyContent = "center";
            overlay.style.zIndex = "10000";
            overlay.style.padding = "1rem";

            const modal = document.createElement("div");
            modal.style.width = "100%";
            modal.style.maxWidth = "440px";
            modal.style.background = "#fff";
            modal.style.borderRadius = "12px";
            modal.style.boxShadow = "0 20px 50px rgba(0, 0, 0, 0.2)";
            modal.style.padding = "1rem";

            const title = document.createElement("h3");
            title.textContent = "Close Opportunity";
            title.style.margin = "0 0 0.5rem";
            title.style.fontSize = "1rem";
            title.style.fontWeight = "600";
            title.style.color = "#111827";

            const description = document.createElement("p");
            description.textContent = "How should this opportunity be closed?";
            description.style.margin = "0";
            description.style.fontSize = "0.875rem";
            description.style.color = "#4b5563";

            const actions = document.createElement("div");
            actions.style.display = "flex";
            actions.style.gap = "0.5rem";
            actions.style.justifyContent = "flex-end";
            actions.style.marginTop = "1rem";

            const cancelButton = document.createElement("button");
            cancelButton.type = "button";
            cancelButton.textContent = "Cancel";
            cancelButton.style.padding = "0.5rem 0.75rem";
            cancelButton.style.borderRadius = "8px";
            cancelButton.style.border = "1px solid #d1d5db";
            cancelButton.style.background = "#fff";
            cancelButton.style.color = "#111827";
            cancelButton.style.cursor = "pointer";

            const lostButton = document.createElement("button");
            lostButton.type = "button";
            lostButton.textContent = "Closed Lost";
            lostButton.style.padding = "0.5rem 0.75rem";
            lostButton.style.borderRadius = "8px";
            lostButton.style.border = "1px solid #ef4444";
            lostButton.style.background = "#fff";
            lostButton.style.color = "#b91c1c";
            lostButton.style.cursor = "pointer";

            const wonButton = document.createElement("button");
            wonButton.type = "button";
            wonButton.textContent = "Closed Won";
            wonButton.style.padding = "0.5rem 0.75rem";
            wonButton.style.borderRadius = "8px";
            wonButton.style.border = "1px solid #2563eb";
            wonButton.style.background = "#2563eb";
            wonButton.style.color = "#fff";
            wonButton.style.cursor = "pointer";

            const cleanup = () => {
                document.removeEventListener("keydown", handleEscape);
                overlay.remove();
            };

            const finish = (result) => {
                cleanup();
                resolve(result);
            };

            const handleEscape = (event) => {
                if (event.key === "Escape") {
                    finish(null);
                }
            };

            cancelButton.addEventListener("click", () => finish(null));
            lostButton.addEventListener("click", () => finish("lost"));
            wonButton.addEventListener("click", () => finish("won"));
            overlay.addEventListener("click", (event) => {
                if (event.target === overlay) {
                    finish(null);
                }
            });
            document.addEventListener("keydown", handleEscape);

            actions.append(cancelButton, lostButton, wonButton);
            modal.append(title, description, actions);
            overlay.append(modal);
            document.body.append(overlay);
        });
    }

    function resolvePipelineContext(button) {
        if (!button) {
            return null;
        }

        const pipeline = button.closest('[data-crm-pipeline-client]');

        if (!pipeline) {
            return null;
        }

        return {
            clientId: pipeline.dataset.crmPipelineClient || '',
            currentStage: pipeline.dataset.crmPipelineCurrent || '',
            closingResult: pipeline.dataset.crmPipelineClosingResult || '',
            businessName: pipeline.dataset.crmPipelineBusinessName || '',
            address: pipeline.dataset.crmPipelineAddress || '',
            ownerId: pipeline.dataset.crmPipelineOwnerId || '',
        };
    }

    async function updatePipelineStage(clientId, stage, closingResult, context, closingComment) {
        const payload = {
            _method: "PUT",
            editing: true,
            business_name: context.businessName,
            owner: context.ownerId,
            pipeline_stage: stage,
            owner_id: context.ownerId,
        };

        if (closingResult !== undefined) {
            payload.closing_result = closingResult;
        }

        if (closingComment !== undefined) {
            payload.pipeline_comment = closingComment;
        }

        if (context.address !== "") {
            payload.address = context.address;
        }

        await Nova.request().post(`/nova-api/clients/${clientId}`, payload);
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-crm-pipeline-stage]');
        const context = resolvePipelineContext(button);

        if (!button || !context) {
            return;
        }

        const stage = button.dataset.crmPipelineStage;

        if (!stage || stage === context.currentStage) {
            return;
        }

        event.preventDefault();

        let closingResult = null;
        let closingComment;

        if (stage === "closed") {
            if (context.closingResult) {
                closingResult = context.closingResult;
            } else {
                const selectedResult = await selectClosingResult();

                if (selectedResult === null) {
                    return;
                }

                closingResult = selectedResult;
            }

            const comment = window.prompt("Add a closing comment (required).", "");

            if (comment === null) {
                return;
            }

            const trimmed = comment.trim();

            if (trimmed === "") {
                window.alert("A closing comment is required when moving to Closed.");
                return;
            }

            closingComment = trimmed;
        } else {
            closingResult = null;
        }

        try {
            await updatePipelineStage(context.clientId, stage, closingResult, context, closingComment);
            window.location.reload();
        } catch (error) {
            if (error?.response?.data?.message) {
                const details = error.response.data.message;
                if (Nova.error) {
                    Nova.error(details);
                } else {
                    window.alert(details);
                }
                return;
            }
            if (Nova.error) {
                Nova.error("Unable to update the pipeline stage. Please try again.");
            } else {
                window.alert("Unable to update the pipeline stage. Please try again.");
            }
        }
    });
})();
